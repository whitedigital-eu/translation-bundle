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
use WhiteDigital\EntityResourceMapper\EntityResourceMapperBundle;

use function explode;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;

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
        }

        $bufferedOutput = new BufferedOutput();

        $extract = $this->getApplication()?->find('translation:extract');
        $arrayInput = new ArrayInput([
            '--force' => '',
            '--format' => 'json',
            'locale' => $locale,
        ]);

        $extract->run($arrayInput, $bufferedOutput);

        $output->write($bufferedOutput->fetch());

        $command = $this->getApplication()?->find('lexik:translations:import');
        $arguments = [
            '--no-interaction' => '',
            '--cache-clear' => '',
            '--locales' => [$locale],
        ];

        if ($input->getOption('override') ?? false) {
            $arguments['--force'] = '';
        }

        $arrayInput = new ArrayInput($arguments);

        $command->run($arrayInput, $bufferedOutput);

        $output->write($bufferedOutput->fetch());

        foreach ($paths as $filePath) {
            unset($filePath);
        }

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('locale', InputArgument::REQUIRED, 'Locale')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to import file')
            ->addOption('override', 'o', InputOption::VALUE_NONE, 'Override existing values');
    }
}
