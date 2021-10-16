<?php

namespace App\Command;

use App\Exception\ExchangeRatesException;
use App\Exception\FileException;
use App\Service\CsvParserService;
use App\Service\ExchangeRatesService;
use App\Service\FileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CalculateFeeCommand extends Command
{
    private FileService $fileService;
    private ParameterBagInterface $params;
    private CsvParserService $csvParserService;
    private ExchangeRatesService $exchangeRatesService;

    public function __construct(
        $name = null,
        ParameterBagInterface $params,
        FileService $fileService,
        CsvParserService $csvParserService,
        ExchangeRatesService $exchangeRatesService
    ) {
        parent::__construct($name);
        $this->params = $params;
        $this->fileService = $fileService;
        $this->exchangeRatesService = $exchangeRatesService;
        $this->csvParserService = $csvParserService;
    }

    protected static $defaultName = 'fee:calculate';

    protected function configure(): void
    {
        $this
            ->addArgument('filePath', InputArgument::REQUIRED, 'Path to a file with transactions to parse.')
            ->setDescription('Calculate fees for transactions.')
            ->setHelp("This command calculate fees from input file");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting calculations');

        try {
            $this->fileService->checkFileAndExtension(
                $input->getArgument('filePath'),
                $this->params->get('file.extensionOfInputFile')
            );

//            $currencyRates = $this->exchangeRatesService->downloadLatestExchangeRates(
//                $this->params->get('exchangeApi.endpoint'),
//                $this->params->get('exchangeApi.key')
//            );

            $currencyRates = ['EUR' => 1, 'USD' => 1.159911, 'JPY' => 132.671222];

            $this->csvParserService->importCustomersAndTransactions($input->getArgument('filePath'), $currencyRates);
        } catch (FileException $e) {
            $output->writeln("Error with file - {$e->getMessage()}");
        } catch (ExchangeRatesException $e) {
            $output->writeln("Error with ExchangeRates API - {$e->getMessage()}");
        }

        $output->writeln('End of calculations');

        return 0;
    }
}
