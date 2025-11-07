<?php
// src/Command/FetchWikiCommand.php
namespace App\Command;

use App\Entity\WikiArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:fetch-wiki', description: 'Récupère un article Wikipédia aléatoire et le stocke s’il est valide')]
class FetchWikiCommand extends Command
{
    public function __construct(
        private HttpClientInterface $http,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $response = $this->http->request('GET', 'https://fr.wikipedia.org/api/rest_v1/page/random/summary');
        $data = $response->toArray();

        if (!isset($data['pageid'], $data['extract'])) {
            $output->writeln('<error>Aucun article valide.</error>');
            return Command::FAILURE;
        }

        $text = $this->cleanText($data['extract']);

        if (!$this->isPlayable($text)) {
            $output->writeln("<comment>Article ignoré : {$data['title']} ({$data['pageid']})</comment>");
            return Command::SUCCESS;
        }

        $article = (new WikiArticle())
            ->setWikiId($data['pageid'])
            ->setTitle($data['title'])
            ->setText($text);

        $this->em->persist($article);
        $this->em->flush();

        $output->writeln("<info>✅ Article sauvegardé : {$data['title']} ({$data['pageid']})</info>");

        return Command::SUCCESS;
    }

    private function cleanText(string $text): string
    {
        // supprime les (de), (en), (it), etc.
        $text = preg_replace('/\s*\([a-z]{2}\)\s*/i', '', $text);

        // conserve uniquement les lettres françaises, chiffres, ponctuation et espaces
        $text = preg_replace(
            '/[^a-zA-Z0-9À-ÖØ-öø-ÿœŒæÆçÇ\'"(),.!?:;—\-–%\s]/u',
            '',
            $text
        );

        // simplifie les espaces multiples
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    private function isPlayable(string $text): bool
    {
        // au moins 200 caractères, pas un truc de prononciation ou purement technique
        return mb_strlen($text) > 200
            && !str_contains(mb_strtolower($text), 'prononciation')
            && !str_contains($text, 'IPA')
            && !str_contains($text, 'ʃ');
    }


}
