<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Lee el Excel que devolvió una exportación, para verificar sus filas.
 */
class ExportedSpreadsheet
{
    /**
     * @return list<list<mixed>>
     */
    public static function rows(TestResponse $response): array
    {
        $file = $response->baseResponse;
        assert($file instanceof BinaryFileResponse);

        $reader = new Reader;
        $reader->open($file->getFile()->getPathname());
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        return $rows;
    }
}
