<?php
// src/Command/RepairWikiArticlesCommand.php
namespace App\Command;

use App\Entity\WikiArticle;
use App\Repository\WikiArticleRepository;
use App\Service\TextCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:repair-wiki-articles',
    description: 'Refetch + reparse tous les WikiArticle depuis Wikipédia et réécrit le texte nettoyé'
)]
class RepairWikiArticlesCommand extends Command
{
    public function __construct(
        private WikiArticleRepository $articles,
        private HttpClientInterface $http,
        private TextCleaner $cleaner,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max d’articles à traiter', null)
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Décalage dans la liste', 0)
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Pause (ms) entre requêtes', 150)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Ne pas enregistrer, juste simuler')
            ->addOption('title-source', null, InputOption::VALUE_REQUIRED, 'source pour refetch: title|pageid', 'title')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'ID interne de WikiArticle à réparer', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $limit   = $input->getOption('limit') ? (int)$input->getOption('limit') : null;
        $offset  = (int)$input->getOption('offset');
        $sleepMs = (int)$input->getOption('sleep');
        $dry     = (bool)$input->getOption('dry-run');
        $by      = (string)$input->getOption('title-source'); // 'title' ou 'pageid'

        $qb = $this->articles->createQueryBuilder('w')
            ->orderBy('w.id', 'ASC')
            ->setFirstResult($offset);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        /** @var WikiArticle[] $rows */
        $rows = $qb->getQuery()->getResult();

        $id = $input->getOption('id');

        if ($id !== null) {
            $article = $this->articles->find((int)$id);

            if (!$article) {
                $io->error("Aucun article trouvé avec l'id $id");
                return Command::FAILURE;
            }

            // On remplace $rows par un tableau contenant juste cet article
            $rows = [$article];

            $io->section("Mode ID unique : réparation de l'article #$id ({$article->getTitle()})");
        }

        if (!$rows) {
            $io->warning('Aucun article à traiter.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Traitement de %d article(s) (offset=%d, limit=%s, mode=%s, dry=%s)', count($rows), $offset, $limit ?? '∞', $by, $dry ? 'oui' : 'non'));

        $ok = 0; $ko = 0; $updated = 0;
        foreach ($rows as $idx => $article) {
            // --- Build URL
            try {
                if ($by === 'pageid' && $article->getWikiId()) {
                    // API MediaWiki "action" (retourne extrait texte brut)
                    $url = sprintf(
                        'https://fr.wikipedia.org/w/api.php?action=query&prop=extracts&explaintext=1&format=json&pageids=%d',
                        $article->getWikiId()
                    );
                    $res = $this->http->request('GET', $url, [
                        'headers' => ['User-Agent' => 'DaktyloBot/1.0 (+contact@daktylo.fr)'],
                    ])->toArray(false);

                    $pages = $res['query']['pages'] ?? [];
                    $first = $pages ? reset($pages) : null;
                    $rawTitle = $first['title'] ?? $article->getTitle();
                    $extract  = $first['extract'] ?? null;
                } else {
                    // REST v1 summary par Titre
                    $title = $article->getTitle();
                    $url = 'https://fr.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($title);
                    $res = $this->http->request('GET', $url, [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Accept-Language' => 'fr',
                            'User-Agent' => 'DaktyloBot/1.0 (+contact@daktylo.fr)',
                        ],
                    ])->toArray(false);

                    // si 404/erreur, $res n’est pas un tableau attendu
                    $rawTitle = $res['title']   ?? $article->getTitle();
                    $extract  = $res['extract'] ?? null;
                }

                if (empty($extract)) {
                    $io->warning(sprintf('#%d [%d] "%s" → pas d’extrait, skip', $article->getId(), $article->getWikiId() ?? 0, $article->getTitle()));
                    $ko++;
                    usleep($sleepMs * 1000);
                    continue;
                }

                // --- Clean
                $clean = $this->cleaner->clean($extract);

                // --- Compare & persist
                $changed = false;
                if ($clean !== $article->getText()) {
                    $changed = true;
                    if (!$dry) $article->setText($clean);
                }
                if ($rawTitle && $rawTitle !== $article->getTitle()) {
                    $changed = true;
                    if (!$dry) $article->setTitle($rawTitle);
                }

                if ($changed && !$dry) {
                    $this->em->persist($article);
                    // flush par batch
                    if ((($idx + 1) % 50) === 0) {
                        $this->em->flush();
                        $this->em->clear(WikiArticle::class);
                    }
                }

                $ok++;
                if ($changed) $updated++;

                $io->writeln(sprintf(
                    '<info>OK</info> #%d [%s] %s%s',
                    $article->getId(),
                    (string)($article->getWikiId() ?? 'n/a'),
                    $article->getTitle(),
                    $changed ? ' <comment>(updated)</comment>' : ''
                ));
            } catch (\Throwable $e) {
                $ko++;
                $io->error(sprintf('FAIL #%d %s — %s', $article->getId(), $article->getTitle(), $e->getMessage()));
            }

            usleep($sleepMs * 1000);
        }

        if (!$dry) {
            $this->em->flush();
        }

        $io->success(sprintf('Terminé: ok=%d, updated=%d, ko=%d', $ok, $updated, $ko));
        return Command::SUCCESS;
    }
}
