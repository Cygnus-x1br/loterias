<?php

namespace App\Console\Commands;

use App\Models\HistoricalResult;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ImportHistoricalResultsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-historical-results {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical lottery results from an Excel file.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return Command::FAILURE;
        }

        $this->info("Starting import from {$filePath}...");

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Mapeamento das colunas do Excel para os campos do banco de dados
            $columnMap = [
                'Concurso' => 'contest_number',
                'Data Sorteio' => 'draw_date',
                'Ganhadores 15 acertos' => 'winners_15_hits',
                'Rateio 15 acertos' => 'payout_15_hits',
                'Ganhadores 14 acertos' => 'winners_14_hits',
                'Rateio 14 acertos' => 'payout_14_hits',
                'Ganhadores 13 acertos' => 'winners_13_hits',
                'Rateio 13 acertos' => 'payout_13_hits',
                'Ganhadores 12 acertos' => 'winners_12_hits',
                'Rateio 12 acertos' => 'payout_12_hits',
                'Ganhadores 11 acertos' => 'winners_11_hits',
                'Rateio 11 acertos' => 'payout_11_hits',
            ];

            $header = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $header[] = trim($cell->getValue());
                }
            }

            $mappedHeader = [];
            $bolaColumnsFound = 0;
            for ($i = 0; $i < count($header); $i++) {
                $columnName = $header[$i];
                if (isset($columnMap[$columnName])) {
                    $mappedHeader[$columnMap[$columnName]] = $i;
                } elseif (str_starts_with($columnName, 'Bola')) {
                    $ballNumber = (int) str_replace('Bola', '', $columnName);
                    // Considerando que o arquivo tem até Bola10, ajustamos o limite
                    if ($ballNumber >= 1 && $ballNumber <= 15) { // Mantemos 15 como limite máximo para Lotofácil
                        $mappedHeader['Bola'.$ballNumber] = $i;
                        $bolaColumnsFound++;
                    }
                }
            }

            // Verificação de colunas essenciais e alerta sobre as dezenas
            if (! isset($mappedHeader['contest_number']) || ! isset($mappedHeader['draw_date'])) {
                $this->error('Required columns (Concurso, Data Sorteio) not found in the spreadsheet header.');

                return Command::FAILURE;
            }

            if ($bolaColumnsFound < 10) { // Mínimo de 10 bolas para prosseguir com o arquivo atual
                $this->error("Found only {$bolaColumnsFound} 'BolaX' columns. Expected at least 10 based on the provided file preview. Please ensure the file has the correct number of drawn balls.");

                return Command::FAILURE;
            } elseif ($bolaColumnsFound < 15) {
                $this->warn("Warning: The spreadsheet contains only {$bolaColumnsFound} 'BolaX' columns. Lotofácil typically draws 15 numbers. Please verify if this is the correct data source or if the file is incomplete.");
            }

            $this->output->progressStart($highestRow - 1);

            DB::beginTransaction();
            $importedCount = 0;

            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                foreach ($sheet->getRowIterator($row, $row) as $rowCells) {
                    $cellIterator = $rowCells->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    foreach ($cellIterator as $cell) {
                        $rowData[] = $cell->getValue();
                    }
                }

                $data = [];
                $drawnNumbers = [];

                foreach ($mappedHeader as $dbColumn => $excelColumnIndex) {
                    $value = $rowData[$excelColumnIndex] ?? null;

                    if (str_starts_with($dbColumn, 'Bola')) {
                        if ($value !== null) {
                            $drawnNumbers[] = (int) $value;
                        }
                    } else {
                        switch ($dbColumn) {
                            case 'draw_date':
                                if (is_numeric($value)) {
                                    // Se for um número de série de data do Excel
                                    $data[$dbColumn] = Carbon::instance(Date::excelToDateTimeObject($value))->format('Y-m-d');
                                } else {
                                    // Se for uma string, tenta parsear com o formato DD/MM/YYYY
                                    try {
                                        $data[$dbColumn] = Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
                                    } catch (\Exception $e) {
                                        // Fallback para Carbon::parse se o formato específico falhar
                                        $this->warn("Could not parse date '{$value}' in row {$row} with 'd/m/Y' format. Trying Carbon::parse as fallback.");
                                        $data[$dbColumn] = Carbon::parse($value)->format('Y-m-d');
                                    }
                                }
                                break;
                            case 'contest_number':
                                $data[$dbColumn] = (int) $value;
                                break;
                            case 'winners_15_hits':
                            case 'winners_14_hits':
                            case 'winners_13_hits':
                            case 'winners_12_hits':
                            case 'winners_11_hits':
                                $data[$dbColumn] = (int) ($value ?? 0);
                                break;
                            case 'payout_15_hits':
                            case 'payout_14_hits':
                            case 'payout_13_hits':
                            case 'payout_12_hits':
                            case 'payout_11_hits':
                                // Remover caracteres não numéricos e substituir vírgula por ponto
                                $cleanedValue = str_replace(['.', ','], ['', '.'], $value);
                                $data[$dbColumn] = (float) ($cleanedValue ?? 0.00);
                                break;
                            default:
                                $data[$dbColumn] = $value;
                                break;
                        }
                    }
                }

                sort($drawnNumbers);
                $data['drawn_numbers'] = $drawnNumbers;
                $data['drawn_numbers_hash'] = HistoricalResult::generateDrawnNumbersHash($drawnNumbers);

                HistoricalResult::updateOrCreate(
                    ['contest_number' => $data['contest_number']],
                    $data
                );
                $importedCount++;
                $this->output->progressAdvance();
            }

            DB::commit();
            $this->output->progressFinish();
            $this->info("Import completed successfully! {$importedCount} records processed.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->output->progressFinish();
            $this->error('An error occurred during import: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
