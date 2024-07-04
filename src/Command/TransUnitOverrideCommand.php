<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Command;

use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use WhiteDigital\EntityResourceMapper\EntityResourceMapperBundle;

use function array_unique;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function implode;
use function json_decode;
use function json_encode;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

#[AsCommand(
    name: 'wd:trans_unit:override',
    description: 'Override i18n translations',
)]
class TransUnitOverrideCommand extends Command
{
    use Traits\Common;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    /**
     * @throws ExceptionInterface
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ['locales' => $locales, 'paths' => $paths] = $this->getLocalesAndPaths($input, false);

        $bufferedOutput = new BufferedOutput();
        $newPath = getcwd() . DIRECTORY_SEPARATOR . 'translations';
        $domains = [];
        foreach ($paths as $locale => $path) {
            $filename = getcwd() . '/' . $path;
            $json = json_decode(file_get_contents($filename), true, 512, JSON_THROW_ON_ERROR);
            $translations = EntityResourceMapperBundle::makeOneDimension($json, onlyLast: true);

            $content = [];
            foreach ($translations as $key => $value) {
                [$domain, $k] = explode('.', $key, 2);
                $content[$domain][$k] = $value;
                $domains[] = $domain;
            }

            foreach ($content as $domain => $translations) {
                $filePath = $newPath . DIRECTORY_SEPARATOR . $domain . '.' . $locale . '.json';
                file_put_contents($filePath, json_encode($translations, JSON_THROW_ON_ERROR));
            }
        }
        $domains = array_unique($domains);

        if ([] === $domains) {
            throw new RuntimeException('Override only works with input file');
        }

        $command = $this->getApplication()?->find('lexik:translations:import');
        $arguments = [
            '--no-interaction' => true,
            '--cache-clear' => true,
            '--locales' => $locales,
            '--force' => true,
            '--domains' => implode(',', $domains),
        ];

        $arrayInput = new ArrayInput($arguments);

        $command->run($arrayInput, $bufferedOutput);

        $output->write($bufferedOutput->fetch());

        $this->cleanup();

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->configureCommon(false);
    }
}
