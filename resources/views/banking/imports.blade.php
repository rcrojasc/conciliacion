@extends('layouts.app')

@section('title', 'Importar cartola · ARIONS FINANCE')

@section('content')

<div class="page-head">
    <div>
        <span class="eyebrow">
            Ingesta bancaria
        </span>

        <h1>Importar cartola</h1>

        <p>
            Carga archivos CSV o Excel. ARIONS FINANCE intentará
            reconocer automáticamente el formato bancario.
        </p>
    </div>
</div>

@if (session('success'))
    <div class="panel" style="margin-bottom: 18px;">
        {{ session('success') }}
    </div>
@endif

@if (session('warning'))
    <div class="panel" style="margin-bottom: 18px;">
        {{ session('warning') }}
    </div>
@endif

@if ($errors->any())
    <div class="panel" style="margin-bottom: 18px;">
        <strong>
            No fue posible procesar la cartola.
        </strong>

        <ul>
            @foreach ($errors->all() as $error)
                <li>
                    {{ $error }}
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid-2 import-grid">

    <section class="panel">

        <div class="panel-head">
            <div>
                <span class="eyebrow">
                    Nueva importación
                </span>

                <h2>
                    Seleccionar archivo
                </h2>
            </div>
        </div>

        <form
            method="POST"
            enctype="multipart/form-data"
            action="{{ route('banking.imports.store') }}"
            class="stack"
        >
            @csrf

            <label>
                Cuenta bancaria

                <select
                    name="bank_account_id"
                    required
                >
                    <option value="">
                        Seleccionar…
                    </option>

                    @foreach ($accounts as $account)
                        <option
                            value="{{ $account->id }}"
                            @selected(
                                old('bank_account_id') === $account->id
                            )
                        >
                            {{ $account->bank?->name }}
                            ·
                            {{ $account->name }}
                            ·
                            {{ $account->currency }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Cartola

                <input
                    type="file"
                    name="file"
                    accept=".csv,.xlsx,.xls"
                    required
                >

                <small>
                    Formatos permitidos: CSV, XLSX y XLS.
                    Máximo 20 MB.
                </small>
            </label>

            <div class="info">
                <strong>
                    Detección automática
                </strong>

                <br>

                Si ARIONS FINANCE reconoce la estructura bancaria,
                detectará automáticamente fecha, operación,
                descripción, abonos, cargos y saldo.
            </div>

            <details>
                <summary>
                    Mapeo manual para formatos no reconocidos
                </summary>

                <div
                    class="form-grid"
                    style="margin-top: 14px;"
                >
                    <label>
                        Columna fecha

                        <input
                            name="date_column"
                            value="{{ old('date_column') }}"
                            placeholder="fecha"
                        >
                    </label>

                    <label>
                        Columna monto

                        <input
                            name="amount_column"
                            value="{{ old('amount_column') }}"
                            placeholder="monto"
                        >
                    </label>

                    <label>
                        Columna descripción

                        <input
                            name="description_column"
                            value="{{ old('description_column') }}"
                            placeholder="descripcion"
                        >
                    </label>

                    <label>
                        Columna referencia

                        <input
                            name="reference_column"
                            value="{{ old('reference_column') }}"
                            placeholder="referencia"
                        >
                    </label>
                </div>
            </details>

            <button
                type="submit"
                class="primary"
            >
                Procesar cartola
            </button>

        </form>

    </section>

    <section class="panel">

        <div class="panel-head">
            <div>
                <span class="eyebrow">
                    Historial
                </span>

                <h2>
                    Importaciones
                </h2>
            </div>
        </div>

        @forelse ($imports as $import)

            <div class="import-row">

                <div>
                    <b>
                        {{ $import->metadata['original_name'] ?? 'Cartola' }}
                    </b>

                    <small>
                        {{ $import->account?->bank?->name }}
                        ·
                        {{ $import->created_at?->format('d/m/Y H:i') }}
                    </small>

                    @if (!empty($import->metadata['detected_format']))
                        <small>
                            Formato:
                            {{ $import->metadata['detected_format'] }}
                        </small>
                    @endif
                </div>

                <div class="right">

                    <span class="badge">
                        {{ $import->status }}
                    </span>

                    <small>
                        {{ $import->rows_imported }} importados
                        ·
                        {{ $import->rows_duplicate }} duplicados
                        ·
                        {{ $import->rows_failed }} errores
                    </small>

                </div>

            </div>

        @empty

            <div class="empty boxed">
                Aún no has cargado cartolas.
            </div>

        @endforelse

        {{ $imports->links() }}

    </section>

</div>

@endsection
