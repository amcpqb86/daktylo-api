<?php
// src/Command/CleanTextFileCommand.php

namespace App\Command;

use App\Service\TextCleaner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-text-file',
    description: 'Nettoie un fichier .txt avec TextCleaner pour le rendre prêt à taper'
)]
class CleanTextFileCommand extends Command
{
    public function __construct(
        private readonly TextCleaner $textCleaner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::REQUIRED, 'Chemin du fichier source (.txt)')
            ->addArgument(
                'output',
                InputArgument::OPTIONAL,
                'Chemin du fichier nettoyé (par défaut : &lt;input&gt;.clean.txt)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $inputPath = (string) $input->getArgument('input');
        $outputPath = $input->getArgument('output');

        if (!is_file($inputPath)) {
            $io->error(sprintf('Fichier "%s" introuvable.', $inputPath));
            return Command::FAILURE;
        }

        if ($outputPath === null) {
            // mon-texte.txt -> mon-texte.clean.txt
            $outputPath = preg_replace('/(\.txt)?$/i', '', $inputPath) . '.clean.txt';
        }

        $raw = file_get_contents($inputPath);
        if ($raw === false) {
            $io->error('Impossible de lire le fichier d’entrée.');
            return Command::FAILURE;
        }

        $cleaned = $this->textCleaner->clean($raw);

        if (file_put_contents($outputPath, $cleaned) === false) {
            $io->error('Impossible d’écrire le fichier de sortie.');
            return Command::FAILURE;
        }

        $io->success(sprintf('Fichier nettoyé enregistré dans : %s', $outputPath));

        return Command::SUCCESS;
    }
}
