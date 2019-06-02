<?php

namespace App\Command;


use App\Controller\MetricsController;
use App\Service\FileSystemExtended;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParseGeoLogs extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            // имя команды (часть после "bin/console")
            ->setName('app:parse-geo-logs')
            // краткое описание, отображающееся при запуске "php bin/console list"
            ->setDescription('Reads logs from specified dir and writes them into table geo_logs.')
            // полное описание команды, отображающееся при запуске команды с опцией "--help"
            ->setHelp('Specify the directory with logs that need to be parsed into database. Logs must be in .csv files.')
            // входящий аргумент, указывающий на директорию, где лежат лог-файлы
            ->addArgument('dir_src', InputArgument::REQUIRED, 'Where are your logs?')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parse_path = (string)$input->getArgument('dir_src');

        $file_system = new FileSystemExtended();
        $metrics_controller = new MetricsController($file_system, $this->em);

        $metrics_controller->processLogsFromFolder($parse_path);

        if($metrics_controller->error) {
            if(!empty($metrics_controller->error_messages)) {
                $output_messages = $metrics_controller->error_messages;
            } else {
                $output_messages = ["Возникла непредвиденная ошибка."];
            }
        } else {
            $output_messages = ["Все файлы были успешно обработаны."];
        }

        $output->writeln($output_messages);
    }
}