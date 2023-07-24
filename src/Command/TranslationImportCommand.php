<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use WhiteDigital\EntityResourceMapper\EntityResourceMapperBundle;
use WhiteDigital\Translation\Entity\Translation;

use function explode;
use function file_exists;
use function file_get_contents;
use function getcwd;
use function json_decode;
use function sprintf;

#[AsCommand(
    name: 'wd:translation:import',
    description: 'Import i18n translations into db structure',
)]
class TranslationImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('locale', InputArgument::REQUIRED, 'Locale')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to import file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locale = $input->getArgument('locale');
        $path = $input->getArgument('path');

        if (!file_exists($filename = getcwd() . '/' . $path)) {
            throw new FileNotFoundException(sprintf('File %s does not exist', $filename));
        }

        $json = json_decode(file_get_contents($filename), true);
        $translations = EntityResourceMapperBundle::makeOneDimension($json, onlyLast: true);
        foreach ($translations as $key => $value) {
            [$domain, $k] = explode('.', $key, 2);
            $object = $this->em->getRepository(Translation::class)->findOneBy(['locale' => $locale, 'domain' => $domain, 'key' => $k, 'isActive' => true]);

            if (null !== $object) {
                continue;
            }

            $object = (new Translation())
                ->setDomain($domain)
                ->setKey($k)
                ->setIsActive(true)
                ->setLocale($locale)
                ->setTranslation($value);

            $this->em->persist($object);
        }

        $this->em->flush();

        return Command::SUCCESS;
    }
}
