<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Command\Traits;

use Lexik\Bundle\TranslationBundle\Entity\Translation;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use WhiteDigital\SiteTree\Entity\SiteTree;

use function array_combine;
use function array_map;
use function class_exists;
use function count;
use function explode;
use function getcwd;
use function unlink;

trait Common
{
    protected function configureCommon(bool $required = true): void
    {
        if (class_exists(SiteTree::class)) {
            $trees = $this->entityManager->getRepository(SiteTree::class)->findBy(['parent' => null, 'isActive' => true]);
            foreach (array_map(static fn (SiteTree $tree) => $tree->getSlug(), $trees) as $locale) {
                $this->addOption($locale, null, $required ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL, 'File path for ' . $locale . ' translations', null);
            }
        } else {
            $this->addOption('locales', null, $required ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL, 'Required locales, comma separated');
            $this->addOption('files', null, $required ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL, 'Required files in order of locales argument, comma separated');
        }
    }

    protected function getLocalesAndPaths(InputInterface $input, bool $required = true): array
    {
        $locales = $paths = [];
        if (class_exists(SiteTree::class)) {
            $trees = $this->entityManager->getRepository(SiteTree::class)->findBy(['parent' => null, 'isActive' => true]);
            foreach (array_map(static fn (SiteTree $tree) => $tree->getSlug(), $trees) as $locale) {
                $locales[] = $locale;
                $paths[$locale] = ($file = $input->getOption($locale));
                if ((null === $file) && $required) {
                    throw new RuntimeException();
                }
            }
            if ($required && count($locales) !== count($trees)) {
                throw new RuntimeException();
            }
        } else {
            $locales = explode(',', $input->getOption('locales') ?? '');
            $paths = explode(',', $input->getOption('files') ?? '');
            if (count($locales) !== count($paths)) {
                throw new RuntimeException();
            }

            $paths = array_combine($locales, $paths);
        }

        return [
            'locales' => $locales,
            'paths' => $paths,
        ];
    }

    protected function cleanup(): void
    {
        $finder = new Finder();
        $finder->files()->in(getcwd() . '/translations')->name('*.json');
        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            @unlink($filePath);
        }
    }

    private function deleteCache(): void
    {
        if (null === $this->whitedigitalTranslationCache || null === $this->bag->get('whitedigital.translation.cache_pool')) {
            return;
        }

        foreach ($this->entityManager->getRepository(Translation::class)->createQueryBuilder('t')->select('t.locale')->distinct()->getQuery()->getSingleColumnResult() as $locale) {
            $this->whitedigitalTranslationCache->delete('list.' . $locale);
        }
    }
}
