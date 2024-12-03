<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Command\Traits;

use Gedmo\Translatable\Translatable;
use Lexik\Bundle\TranslationBundle\Entity\Translation;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use WhiteDigital\SiteTree\Entity\SiteTree;

use function array_combine;
use function array_key_exists;
use function array_map;
use function array_merge;
use function class_exists;
use function count;
use function explode;
use function getcwd;
use function unlink;

trait Common
{
    protected string $defaultLocale;

    protected function setDefaultLocale(): void
    {
        $this->defaultLocale = $this->bag->get('kernel.default_locale');
        if ($this->bag->has('stof_doctrine_extensions.default_locale')) {
            $this->defaultLocale = $this->bag->get('stof_doctrine_extensions.default_locale');
        }
    }

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
                    throw new RuntimeException('missing locale: ' . $locale);
                }
            }
            if ($required && count($locales) !== count($trees)) {
                throw new RuntimeException('missing locales or paths');
            }
        } else {
            $locales = explode(',', $input->getOption('locales') ?? '');
            $paths = explode(',', $input->getOption('files') ?? '');
            if (count($locales) !== count($paths)) {
                throw new RuntimeException('wrong locales');
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

    /**
     * @throws InvalidArgumentException
     */
    private function deleteCache(): void
    {
        if (null === $this->whitedigitalTranslationCache || null === $this->bag->get('whitedigital.translation.cache_pool')) {
            return;
        }

        foreach ($this->entityManager->getRepository(Translation::class)->createQueryBuilder('t')->select('t.locale')->distinct()->getQuery()->getSingleColumnResult() as $locale) {
            $this->whitedigitalTranslationCache->delete('whitedigital.translation.list.' . $locale);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function findAllImplementingInterface(): array
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $result = [];

        foreach ($metadata as $classMetadata) {
            $entityClass = $classMetadata->getName();
            $entity = new ReflectionClass($entityClass);
            if (!$entity->isAbstract() && $entity->implementsInterface(Translatable::class)) {
                $result[] = $this->entityManager->getRepository($entityClass)->findAll();
            }
        }

        return array_merge(...$result);
    }

    /**
     * @throws ReflectionException
     */
    private function migrate(): void
    {
        $batchSize = 100;
        $entities = $this->findAllImplementingInterface();
        $translationRepository = $this->entityManager->getRepository(\Gedmo\Translatable\Entity\Translation::class);

        foreach ($entities as $i => $entity) {
            foreach ((new ReflectionClass($entity))->getProperties() as $property) {
                $existingTranslations = $translationRepository->findTranslations($entity);
                $existingTranslations[$this->defaultLocale] ??= [];
                if ([] !== $property->getAttributes(\Gedmo\Mapping\Annotation\Translatable::class)) {
                    $property->setAccessible(true);

                    $propertyName = $property->getName();

                    if (!array_key_exists($propertyName, $existingTranslations[$this->defaultLocale])) {
                        $value = $property->getValue($entity);
                        $translationRepository->translate($entity, $propertyName, $this->defaultLocale, $value);
                        $existingTranslations[$this->defaultLocale][$propertyName] = $value;
                    }
                }
            }

            if (($i + 1) % $batchSize === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
    }
}
