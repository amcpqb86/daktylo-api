<?php

namespace App\Command;

use App\Entity\WikiArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:clean-wiki-texts', description: 'Nettoie tous les articles Wiki en base pour les rendre tapables')]
class CleanWikiTextsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
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
            $newText = $this->cleanText($oldText);

            if ($newText !== $oldText) {
                $article->setText($newText);
                $count++;
            }
        }

        $this->em->flush();

        $output->writeln("<info>✅ $count articles nettoyés et mis à jour.</info>");
        return Command::SUCCESS;
    }

    private function cleanText(string $text): string
    {
        // Normalisation des apostrophes et guillemets typographiques → tapables
        $text = str_replace(
            ['’', '‘', '“', '”', '«', '»'],
            ["'", "'", '"', '"', '"', '"'],
            $text
        );

        // Supprime les (de), (en), (it), etc.
        $text = preg_replace('/\s*\([a-z]{2}\)\s*/i', '', $text);

        // Supprime les accents sur les MAJUSCULES uniquement
        $text = strtr($text, [
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'Ç' => 'C',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Î' => 'I', 'Ï' => 'I',
            'Ô' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ÿ' => 'Y'
        ]);

        // Conserve uniquement les caractères autorisés (lettres FR, ponctuation, espaces)
        $text = preg_replace(
            '/[^a-zA-Z0-9à-öø-ÿœŒæÆçÇ\'"(),.!?:;—\-–%\s]/u',
            '',
            $text
        );

        // Simplifie les espaces multiples
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
