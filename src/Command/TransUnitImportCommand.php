<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Command;

use JsonException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Finder\Finder;
use WhiteDigital\EntityResourceMapper\EntityResourceMapperBundle;

use function explode;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function implode;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function rename;
use function sprintf;
use function str_replace;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

#[AsCommand(
    name: 'wd:trans_unit:import',
    description: 'Import i18n translations into lexik trans unit',
)]
class TransUnitImportCommand extends Command
{
    /**
     * @throws ExceptionInterface
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locale = $input->getArgument('locale');
        $path = $input->getArgument('path');

        $domains = [];
        if (null !== $path) {
            if (!is_file($filename = getcwd() . '/' . $path)) {
                throw new FileNotFoundException(sprintf('File %s does not exist', $filename));
            }

            $newPath = getcwd() . DIRECTORY_SEPARATOR . 'translations';

            if (!is_dir($newPath) && !mkdir($newPath, recursive: true) && !is_dir($newPath)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $newPath));
            }

            $json = json_decode(file_get_contents($filename), true, 512, JSON_THROW_ON_ERROR);
            $translations = EntityResourceMapperBundle::makeOneDimension($json, onlyLast: true);

            $content = [];
            foreach ($translations as $key => $value) {
                [$domain, $k] = explode('.', $key, 2);
                $content[$domain][$k] = $value;
            }

            $paths = [];
            foreach ($content as $domain => $translations) {
                $paths[] = $filePath = $newPath . DIRECTORY_SEPARATOR . $domain . '.' . $locale . '.json';
                file_put_contents($filePath, json_encode($translations, JSON_THROW_ON_ERROR));
                $domains[] = $domain;
            }
        }

        $bufferedOutput = new BufferedOutput();

        $extract = $this->getApplication()?->find('translation:extract');
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

        $command = $this->getApplication()?->find('lexik:translations:import');
        $arguments = [
            '--no-interaction' => true,
            '--cache-clear' => true,
            '--locales' => [$locale],
        ];

        if ($input->getOption('override') ?? false) {
            if ([] === $domains) {
                throw new RuntimeException('Override only works with input file');
            }

            $arguments['--force'] = true;
            $arguments['--domains'] = implode(',', $domains);
        }

        $arrayInput = new ArrayInput($arguments);

        $command->run($arrayInput, $bufferedOutput);

        $output->write($bufferedOutput->fetch());

        if (null !== $path) {
            foreach ($paths as $filePath) {
                unset($filePath);
            }
        }

        $finder = new Finder();
        $finder->files()->in(getcwd() . '/translations')->name('*.json');
        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            @unlink($filePath);
        }

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('locale', InputArgument::REQUIRED, 'Locale')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to import file')
            ->addOption('override', 'o', InputOption::VALUE_NONE, 'Override existing values');
    }
}
