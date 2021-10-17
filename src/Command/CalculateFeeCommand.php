<?php

namespace App\Command;

use App\CurrenciesConfigParserTrait;
use App\Exception\ExchangeRatesException;
use App\Exception\FileException;
use App\Service\CommissionFeeService;
use App\Service\ExchangeRatesService;
use App\Service\FileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CalculateFeeCommand extends Command
{
    use CurrenciesConfigParserTrait;

    private ContainerInterface $container;
    private FileService $fileService;
    private CommissionFeeService $commissionFeeService;
    private ExchangeRatesService $exchangeRatesService;

    /**
     * Array with accepted currencies.
     *
     * @var array
     */
    private array $acceptedCurrencies;

    public function __construct(
        ContainerInterface $container,
        FileService $fileService,
        CommissionFeeService $commissionFeeService,
        ExchangeRatesService $exchangeRatesService,
        $name = null
    ) {
        parent::__construct($name);
        $this->container = $container;
        $this->fileService = $fileService;
        $this->exchangeRatesService = $exchangeRatesService;
        $this->commissionFeeService = $commissionFeeService;
        $this->acceptedCurrencies = $this->getParsedCurrenciesConfig($this->container, 'accept');
    }

    protected static $defaultName = 'fee:calculate';

    protected function configure(): void
    {
        $this
            ->addArgument('filePath', InputArgument::REQUIRED, 'Path to a file with transactions to parse.')
            ->addArgument('demoMode', InputArgument::OPTIONAL, 'Mode with no live download of exchange rates.')
            ->setDescription('Calculate fees for transactions.')
            ->setHelp("This command calculate fees from input file");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->fileService->checkFileAndExtension(
                $input->getArgument('filePath'),
                $this->container->getParameter('file.extensionOfInputFile')
            );

            if (!is_null($input->getArgument('demoMode'))) {
                $currencyRates = ['EUR' => 1, 'USD' => 1.1497, 'JPY' => 129.53];
            } else {
                $currencyRates = $this->exchangeRatesService->downloadLatestExchangeRates(
                    $this->container->getParameter('exchangeApi.endpoint'),
                    $this->container->getParameter('exchangeApi.key')
                );
            }

            $commissionFees = $this->commissionFeeService->calculateFee(
                $input->getArgument('filePath'),
                $this->acceptedCurrencies,
                $currencyRates
            );

            if (!$commissionFees->isEmpty()) {
                $feeCounter = $commissionFees->count();
                $printCounter = 1;
                foreach ($commissionFees as $commission) {
                    if ($feeCounter === $printCounter) {
                        $output->write($commission);
                    } else {
                        $output->writeln($commission);
                    }
                    $printCounter++;
                }
            }
        } catch (FileException $e) {
            $output->writeln("Error with file - {$e->getMessage()}");
        } catch (ExchangeRatesException $e) {
            $output->writeln("Error with ExchangeRates API - {$e->getMessage()}");
        }

        return 0;
    }
}
