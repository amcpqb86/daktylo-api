<?php

namespace App\Command;

use App\Entity\WikiArticle;
use App\Service\TextCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:clean-and-filter-wiki-articles',
    description: 'Nettoie tous les articles Wiki et supprime ceux qui ne sont pas jouables'
)]
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

        $updated = 0;
        $removed = 0;

        foreach ($articles as $article) {
            $oldText = $article->getText() ?? '';
            $newText = $this->textCleaner->clean($oldText);

            $meta = [
                'title'   => $article->getTitle(),
                'extract' => $article->getText(),
            ];

            $isPlayable      = $this->isPlayable($newText, $meta);
            $hasGameSessions = !$article->getGameSessions()->isEmpty();

            if (!$isPlayable && !$hasGameSessions) {
                $this->em->remove($article);
                $removed++;
                continue;
            }

            if ($newText !== $oldText) {
                $article->setText($newText);
                $updated++;
            }
        }

        $this->em->flush();

        $output->writeln("<info>✅ $updated articles nettoyés et conservés.</info>");
        $output->writeln("<comment>🗑️ $removed articles supprimés car non jouables et sans parties associées.</comment>");

        return Command::SUCCESS;
    }

    private function isPlayable(string $text, array $meta): bool
    {
        $title = mb_strtolower($meta['title'] ?? '');
        $rawExtract = mb_strtolower($meta['extract'] ?? '');
        $lowerText = mb_strtolower($text);

        if (preg_match('/^(décès|naissance)s? en \d{3,4}$/u', $title)) {
            return false;
        }

        if (str_starts_with($title, 'liste de ') || str_starts_with($title, 'liste des ')) {
            return false;
        }

        if (preg_match('/^\d{3,4}( en .+)?$/u', $title)) {
            return false;
        }

        if (str_starts_with($rawExtract, 'cette page dresse une liste')) {
            return false;
        }

        return mb_strlen($text) > 100
            && !str_contains($lowerText, 'prononciation')
            && !str_contains($text, 'IPA')
            && !str_contains($text, 'ʃ');
    }
}
