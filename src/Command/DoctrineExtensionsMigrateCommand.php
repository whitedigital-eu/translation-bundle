<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Command;

use Doctrine\ORM\EntityManagerInterface;
use ReflectionException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'wd:doctrine:migrate',
    description: 'Migrate entity translations to doctrine extensions format',
)]
class DoctrineExtensionsMigrateCommand extends Command
{
    use Traits\Common;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $bag,
    ) {
        parent::__construct();
        $this->setDefaultLocale();
    }

    /**
     * @throws ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->migrate();

        return Command::SUCCESS;
    }
}
