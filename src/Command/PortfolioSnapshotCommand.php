<?php

namespace App\Command;

use App\Entity\PortfolioSnapshot;
use App\Repository\UserRepository;
use App\Service\PortfolioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:portfolio:snapshot',
    description: 'Enregistre l\'état du portefeuille',
)]
class PortfolioSnapshotCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PortfolioService $portfolioService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // On récupère les utilisateurs
        $users = $this->userRepository->findAll();

        try {
            foreach ($users as $user) {
                $data = $this->portfolioService->calculateCurrentSnapshot($user);

                $snapshot = new PortfolioSnapshot();
                $snapshot->setOwner($user);
                $snapshot->setCreatedAt(new \DateTimeImmutable());
                $snapshot->setTotalEquity($data['total_equity']);
                $snapshot->setCashAmount($data['cash_amount']);

                $this->entityManager->persist($snapshot);
            }
        } catch (\Exception $e) {
            $output->writeln('Erreur lors du calcul du snapshot : '.$e->getMessage());

            return Command::FAILURE;
        }

        $this->entityManager->flush();

        // Récupère la date et l'heure actuelles
        $now = new \DateTimeImmutable();
        $timestamp = $now->format('['.\DateTimeInterface::ATOM.']'); // Format : [2026-05-18T18:05:00+02:00]

        // Utilisation de termes sans accents pour éviter les problèmes d'encodage de la console Cron
        $output->writeln(sprintf('%s [SUCCESS] Snapshot enregistre avec succes !', $timestamp));

        return Command::SUCCESS;
    }
}
