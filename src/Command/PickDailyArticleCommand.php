<?php

namespace App\Command;

use App\Entity\DailyArticle;
use App\Repository\DailyArticleRepository;
use App\Repository\WikiArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:pick-daily-article',
    description: 'Choisit un article Wikipédia pour le défi du lendemain',
)]
class PickDailyArticleCommand extends Command
{
    public function __construct(
        private DailyArticleRepository $dailyArticleRepository,
        private WikiArticleRepository $wikiArticleRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetDate = (new \DateTimeImmutable('tomorrow'))->setTime(0, 0, 0);

        if ($this->dailyArticleRepository->findByDate($targetDate)) {
            $output->writeln('Déjà défini pour ' . $targetDate->format('d/m/Y'));
            return Command::SUCCESS;
        }

        $alreadyPicked = $this->dailyArticleRepository->createQueryBuilder('d')
            ->select('IDENTITY(d.article) as id')
            ->getQuery()
            ->getSingleColumnResult();

        $article = $this->wikiArticleRepository->findOneRandomNotInDaily($alreadyPicked);

        if (!$article) {
            $output->writeln('Aucun article disponible.');
            return Command::FAILURE;
        }

        $daily = new DailyArticle();
        $daily->setArticle($article);
        $daily->setDate($targetDate);

        $this->em->persist($daily);
        $this->em->flush();

        $output->writeln('Article du ' . $targetDate->format('d/m/Y') . ' : ' . $article->getTitle());

        return Command::SUCCESS;
    }
}
