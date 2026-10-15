<?php

namespace App\Http\Tables;

use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tabla de un listado resuelta en el servidor: búsqueda, filtros, orden por columna, paginación
 * y exportación (Excel y PDF) con exactamente los mismos filtros y orden que se ven en pantalla.
 * Cada listado declara sus columnas ordenables, su consulta y cómo se ve y se exporta una fila.
 *
 * Parámetros de la petición: search, sort, direction (asc|desc), per_page, page y los filtros
 * propios de cada tabla.
 *
 * @template TModel of Model
 */
abstract class DataTable
{
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    /**
     * El PDF se arma en memoria: más filas que esto se exportan solo a Excel.
     */
    public const PDF_MAX_ROWS = 1000;

    public function __construct(protected Request $request) {}

    /**
     * Título del listado: encabezado del PDF y nombre de los archivos exportados.
     */
    abstract public function title(): string;

    /**
     * Consulta con la búsqueda y los filtros de la petición aplicados (sin orden ni paginación).
     *
     * @return Builder<TModel>
     */
    abstract protected function query(): Builder;

    /**
     * Columnas por las que se puede ordenar: clave que envía la vista => columna SQL (o una
     * expresión, p. ej. un nombre calculado).
     *
     * @return array<string, string|Expression>
     */
    abstract protected function sortable(): array;

    /**
     * Orden por defecto: [clave de sortable(), dirección].
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    abstract protected function defaultSort(): array;

    /**
     * Fila tal como la recibe la vista.
     *
     * @param  TModel  $model
     * @return array<string, mixed>
     */
    abstract protected function toRow(Model $model): array;

    /**
     * Columnas exportadas: encabezado => valor de la celda.
     *
     * @return array<string, Closure(TModel): (string|int|float|null)>
     */
    abstract protected function exportColumns(): array;

    /**
     * Filtros propios de la tabla (además de búsqueda, orden y tamaño de página), para que la
     * vista los muestre con su valor actual.
     *
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * Descripción legible de los filtros activos, para el encabezado del PDF.
     *
     * @return list<string>
     */
    protected function filterSummary(): array
    {
        return $this->search() !== '' ? ["Búsqueda: \"{$this->search()}\""] : [];
    }

    public function search(): string
    {
        return $this->request->string('search')->trim()->value();
    }

    /**
     * Página actual con las filas ya transformadas para la vista.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function rows(): LengthAwarePaginator
    {
        return $this->sortedQuery()
            ->paginate($this->perPage())
            ->withQueryString()
            ->through(fn (Model $model): array => $this->toRow($model));
    }

    /**
     * Estado actual de la tabla (búsqueda, orden, tamaño de página y filtros) para la vista.
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        [$sort, $direction] = $this->sort();

        return [
            'search' => $this->search(),
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $this->perPage(),
            ...$this->filters(),
        ];
    }

    public function export(string $format): Response
    {
        return $format === 'pdf' ? $this->exportPdf() : $this->exportExcel();
    }

    /**
     * @return Builder<TModel>
     */
    protected function sortedQuery(): Builder
    {
        [$sort, $direction] = $this->sort();
        $query = $this->query();

        // El id desempata para que la paginación sea estable entre páginas.
        return $query
            ->orderBy($this->sortable()[$sort], $direction)
            ->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    protected function sort(): array
    {
        [$defaultSort, $defaultDirection] = $this->defaultSort();
        $sort = $this->request->string('sort')->value();
        $direction = $this->request->string('direction')->lower()->value();

        if (! array_key_exists($sort, $this->sortable())) {
            return [$defaultSort, $defaultDirection];
        }

        return [$sort, $direction === 'desc' ? 'desc' : 'asc'];
    }

    protected function perPage(): int
    {
        $perPage = $this->request->integer('per_page', 15);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 15;
    }

    protected function exportExcel(): Response
    {
        $path = tempnam(sys_get_temp_dir(), 'export');
        $columns = $this->exportColumns();

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_keys($columns), (new Style)->setFontBold()));

        foreach ($this->sortedQuery()->lazy(500) as $model) {
            $writer->addRow(Row::fromValues(array_map(fn (Closure $value) => $value($model), array_values($columns))));
        }

        $writer->close();

        return response()->download($path, $this->fileName('xlsx'))->deleteFileAfterSend();
    }

    protected function exportPdf(): Response
    {
        $columns = $this->exportColumns();
        $total = $this->sortedQuery()->count();

        $rows = $this->sortedQuery()
            ->limit(self::PDF_MAX_ROWS)
            ->get()
            ->map(fn (Model $model): array => array_map(fn (Closure $value) => $value($model), array_values($columns)));

        return Pdf::loadView('exports.table', [
            'title' => $this->title(),
            'headings' => array_keys($columns),
            'rows' => $rows,
            'total' => $total,
            'limit' => self::PDF_MAX_ROWS,
            'filters' => $this->filterSummary(),
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', count($columns) > 5 ? 'landscape' : 'portrait')->download($this->fileName('pdf'));
    }

    protected function fileName(string $extension): string
    {
        return Str::slug($this->title()).'-'.now()->format('Y-m-d_His').'.'.$extension;
    }
}
