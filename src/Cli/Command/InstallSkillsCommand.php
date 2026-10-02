<?php

namespace Limenet\LaravelBaseline\Cli\Command;

use Limenet\LaravelBaseline\Skills\SkillInstaller;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class InstallSkillsCommand extends ProjectCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('install-skills')
            ->setDescription('Copies the packaged skills into .claude/skills/ without running the checks.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite skills that are already installed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->project($input);

        if ($project === null) {
            return self::FAILURE;
        }

        $result = SkillInstaller::install($project, (bool) $input->getOption('force'));

        foreach ($result['installed'] as $name) {
            $output->writeln("✅ {$name} → .claude/skills/{$name}/SKILL.md");
        }

        foreach ($result['skipped'] as $name) {
            $output->writeln("⏭  {$name} (already installed — pass --force to overwrite)");
        }

        $output->writeln(sprintf(
            "\n%d skill(s) installed, %d left untouched.",
            count($result['installed']),
            count($result['skipped']),
        ));

        return self::SUCCESS;
    }
}
