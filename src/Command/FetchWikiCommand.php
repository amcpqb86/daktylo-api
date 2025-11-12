<?php
// src/Command/FetchWikiCommand.php
namespace App\Command;

use App\Entity\WikiArticle;
use App\Service\TextCleaner;
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
        private EntityManagerInterface $em,
        private TextCleaner $textCleaner
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

        $text = $this->textCleaner->clean($data['extract']);

        if (!$this->isPlayable($text)) {
            $output->writeln("<comment>Article ignoré : {$data['title']} ({$data['pageid']})</comment>");
            return Command::SUCCESS;
        }

        if ($this->em->getRepository(WikiArticle::class)->findOneBy(['wikiId' => $data['pageid']])) {
            $output->writeln("<comment>Article déjà présent en BD : {$data['title']} ({$data['pageid']}</comment>");
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

    private function isPlayable(string $text): bool
    {
        return mb_strlen($text) > 100
            && !str_contains(mb_strtolower($text), 'prononciation')
            && !str_contains($text, 'IPA')
            && !str_contains($text, 'ʃ');
    }


}
