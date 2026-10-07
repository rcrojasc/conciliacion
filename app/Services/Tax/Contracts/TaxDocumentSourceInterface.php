<?php

namespace App\Services\Tax\Contracts;

use App\Data\Tax\TaxDocumentData;
use App\Enums\TaxDocumentDirection;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface TaxDocumentSourceInterface
{
    /**
     * Identificador único de la fuente.
     *
     * Ejemplos:
     * sii_rcv
     * sii_dte_xml
     * manual
     * provider_api
     */
    public function source(): string;

    /**
     * Indica si la fuente está disponible/configurada
     * para una organización determinada.
     */
    public function isAvailable(
        Organization $organization
    ): bool;

    /**
     * Obtiene documentos tributarios desde la fuente
     * y los transforma al contrato interno de ARIONS.
     *
     * @return Collection<int, TaxDocumentData>
     */
    public function fetch(
        Organization $organization,
        TaxDocumentDirection $direction,
        CarbonInterface $from,
        CarbonInterface $to
    ): Collection;
}
