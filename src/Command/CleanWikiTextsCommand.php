<?php

namespace App\Command;

use App\Entity\WikiArticle;
use App\Service\TextCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:clean-wiki-texts', description: 'Nettoie tous les articles Wiki en base pour les rendre tapables')]
class CleanWikiTextsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private TextCleaner $textCleaner
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(WikiArticle::class);
        $articles = $repo->findAll();
        $count = 0;

        foreach ($articles as $article) {
            $oldText = $article->getText();
            $newText = $this->textCleaner->clean($oldText);

            if ($newText !== $oldText) {
                $article->setText($newText);
                $count++;
            }
        }

        $this->em->flush();

        $output->writeln("<info>✅ $count articles nettoyés et mis à jour.</info>");
        return Command::SUCCESS;
    }
}
