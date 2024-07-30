<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Command;

use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Lexik\Bundle\TranslationBundle\Entity\TransUnit;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Cache\CacheInterface;
use WhiteDigital\EntityResourceMapper\EntityResourceMapperBundle;
use WhiteDigital\Translation\Entity\TransUnitIsDeleted;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_shift;
use function basename;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function gettype;
use function in_array;
use function is_array;
use function is_dir;
use function iterator_to_array;
use function json_decode;
use function json_encode;
use function json_last_error;
use function mkdir;
use function rename;
use function sprintf;
use function str_replace;

use const DIRECTORY_SEPARATOR;
use const JSON_ERROR_NONE;
use const JSON_THROW_ON_ERROR;

#[AsCommand(
    name: 'wd:trans_unit:import',
    description: 'Import i18n translations into lexik trans unit',
)]
class TransUnitImportCommand extends Command
{
    use Traits\Common;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $bag,
        private readonly ?CacheInterface $whitedigitalTranslationCache = null,
    ) {
        parent::__construct();
    }

    /**
     * @throws ExceptionInterface
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ['locales' => $locales, 'paths' => $paths] = $this->getLocalesAndPaths($input);

        if (!$this->jsonStructureCompare($paths)) {
            throw new RuntimeException();
        }

        $newPath = getcwd() . DIRECTORY_SEPARATOR . 'translations';

        if (!is_dir($newPath) && !mkdir($newPath, recursive: true) && !is_dir($newPath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $newPath));
        }

        $bufferedOutput = new BufferedOutput();
        $extract = $this->getApplication()?->find('translation:extract');

        foreach ($paths as $locale => $path) {
            $filename = getcwd() . '/' . $path;
            $json = json_decode(file_get_contents($filename), true, 512, JSON_THROW_ON_ERROR);
            $translations = EntityResourceMapperBundle::makeOneDimension($json, onlyLast: true);

            $content = [];
            foreach ($translations as $key => $value) {
                [$domain, $k] = explode('.', $key, 2);
                $content[$domain][$k] = $value;
            }

            foreach ($content as $domain => $translations) {
                $filePath = $newPath . DIRECTORY_SEPARATOR . $domain . '.' . $locale . '.json';
                file_put_contents($filePath, json_encode($translations, JSON_THROW_ON_ERROR));
            }

            $arrayInput = new ArrayInput([
                '--force' => true,
                '--format' => 'json',
                'locale' => $locale,
            ]);

            $extract->run($arrayInput, $bufferedOutput);

            $output->write($bufferedOutput->fetch());

            $finder = new Finder();
            $finder->files()->in(getcwd() . '/translations')->name('*+intl-icu.' . $locale . '.json');
            foreach ($finder as $file) {
                @rename($file->getRealPath(), str_replace('+intl-icu', '', $file->getRealPath()));
            }
        }

        $data = [];
        foreach ($locales as $locale) {
            $finder = new Finder();
            $finder->files()->in(getcwd() . '/translations')->name('*.' . $locale . '.json');

            $data[$locale] = array_keys(EntityResourceMapperBundle::makeOneDimension($this->readJsonFiles(array_keys(iterator_to_array($finder->files())), $locale), onlyLast: true));
        }

        $found = [];
        foreach ($data as $translations) {
            foreach ($translations as $translation) {
                [$domain, $key] = explode('.', $translation, 2);
                if (!isset($found[$domain][$key])) {
                    $found[$domain][$key] = 1;
                }
            }
        }

        $command = $this->getApplication()?->find('lexik:translations:import');
        $arguments = [
            '--no-interaction' => true,
            '--cache-clear' => true,
            '--locales' => $locales,
        ];

        $arrayInput = new ArrayInput($arguments);

        $command->run($arrayInput, $bufferedOutput);

        $output->write($bufferedOutput->fetch());

        $this->cleanup();

        $transUnits = $this->entityManager->getRepository(TransUnit::class)->createQueryBuilder('tu')
            ->select('tu.id')
            ->getQuery()
            ->getSingleColumnResult();

        $addOrUpdate = $this->entityManager->getRepository(TransUnitIsDeleted::class)->createQueryBuilder('td')
            ->select('tu.id')
            ->innerJoin('td.transUnit', 'tu')
            ->getQuery()
            ->getSingleColumnResult();

        $transUnits = array_filter($transUnits, static fn ($transUnit) => !in_array($transUnit, $addOrUpdate, true));

        foreach ($transUnits as $transUnit) {
            $tu = (new TransUnitIsDeleted())
                ->setTransUnit($this->entityManager->getReference(TransUnit::class, $transUnit))
                ->setIsDeleted(false);
            $this->entityManager->persist($tu);
        }
        $this->entityManager->flush();

        $allResultsFound = [];
        foreach ($found as $domain => $keys) {
            $qbSelectFound = $this->entityManager->getRepository(TransUnit::class)->createQueryBuilder('tu');
            $resultSelectFound = $qbSelectFound
                ->select('tu.id')
                ->where('tu.domain = :domain')
                ->setParameter('domain', $domain)
                ->andWhere($qbSelectFound->expr()->in('tu.key', array_keys($keys)))
                ->getQuery()
                ->getSingleColumnResult();

            $allResultsFound[] = $resultSelectFound;
        }

        $allResultsFound = array_merge(...$allResultsFound);

        $qbUpdateFound = $this->entityManager->createQueryBuilder();
        $qbUpdateFound->update(TransUnitIsDeleted::class, 'td')
            ->set('td.isDeleted', ':isDeleted')
            ->setParameter('isDeleted', false)
            ->where($qbUpdateFound->expr()->in('td.transUnit', $allResultsFound))
            ->getQuery()
            ->execute();

        $qbUpdateNotFound = $this->entityManager->createQueryBuilder();
        $qbUpdateNotFound->update(TransUnitIsDeleted::class, 'td')
            ->set('td.isDeleted', ':isDeleted')
            ->setParameter('isDeleted', true)
            ->where($qbUpdateNotFound->expr()->notIn('td.transUnit', $allResultsFound))
            ->getQuery()
            ->execute();

        $qbSelectDomainFound = $this->entityManager->getRepository(TransUnit::class)->createQueryBuilder('tu');
        $resultSelectDomainFound = $qbSelectDomainFound
            ->select('tu.id')
            ->andWhere($qbSelectDomainFound->expr()->in('tu.domain', array_keys($found)))
            ->getQuery()
            ->getSingleColumnResult();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(TransUnitIsDeleted::class, 'td')
            ->set('td.isDeleted', ':isDeleted')
            ->setParameter('isDeleted', true)
            ->andWhere($qb->expr()->notIn('td.transUnit', $resultSelectDomainFound))
            ->getQuery()
            ->execute();

        $this->deleteCache();

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->configureCommon();
    }

    private function jsonStructureCompare(array $jsonFiles): bool
    {
        $arrays = array_map(static fn (string $json) => json_decode(file_get_contents($json), true), $jsonFiles);

        $baseStructure = array_shift($arrays);

        foreach ($arrays as $array) {
            if (!$this->compareStructure($baseStructure, $array)) {
                return false;
            }
        }

        return true;
    }

    private function compareStructure(mixed $array1, mixed $array2): bool
    {
        if (gettype($array1) !== gettype($array2)) {
            return false;
        }

        if (is_array($array1)) {
            foreach ($array1 as $key => $value) {
                if (!array_key_exists($key, $array2) || !$this->compareStructure($value, $array2[$key])) {
                    return false;
                }
            }
            foreach ($array2 as $key => $value) {
                if (!array_key_exists($key, $array1)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function readJsonFiles(array $jsonFiles, string $locale): array
    {
        $result = [];

        foreach ($jsonFiles as $jsonFile) {
            $key = basename($jsonFile, '.' . $locale . '.json');
            $data = json_decode(file_get_contents($jsonFile), true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                continue;
            }

            $result[$key] = $data;
        }

        return $result;
    }
}
