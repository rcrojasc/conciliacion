@extends('layouts.app')

@section('title', 'Cuentas bancarias · ARIONS FINANCE')

@section('content')

<div class="page-head">
    <div>
        <h1>Cuentas bancarias</h1>
        <p>
            Administra las cuentas bancarias asociadas a la organización activa.
        </p>
    </div>

    <div>
        <a
            href="{{ route('banking.accounts.create') }}"
            class="btn"
        >
            + Nueva cuenta bancaria
        </a>
    </div>
</div>

@if (session('success'))
    <div
        class="card"
        style="
            margin-bottom: 20px;
            padding: 14px 18px;
        "
    >
        {{ session('success') }}
    </div>
@endif

<div class="card">

    @if ($accounts->isEmpty())

        <div style="padding: 30px; text-align: center;">

            <h3>No existen cuentas bancarias configuradas</h3>

            <p>
                Registra la primera cuenta bancaria para comenzar
                a importar movimientos y realizar conciliaciones.
            </p>

            <a
                href="{{ route('banking.accounts.create') }}"
                class="btn"
            >
                + Registrar primera cuenta
            </a>

        </div>

    @else

        <div style="overflow-x: auto;">

            <table style="width: 100%;">

                <thead>
                    <tr>
                        <th>Banco</th>
                        <th>Alias</th>
                        <th>Tipo</th>
                        <th>Cuenta</th>
                        <th>Moneda</th>
                        <th>Movimientos</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach ($accounts as $account)

                        <tr>

                            <td>
                                <strong>
                                    {{ $account->bank?->name ?? 'Banco no disponible' }}
                                </strong>
                            </td>

                            <td>
                                {{ $account->name ?: 'Sin alias' }}
                            </td>

                            <td>
                                @switch($account->account_type)

                                    @case('checking')
                                        Cuenta corriente
                                        @break

                                    @case('sight')
                                        Cuenta vista
                                        @break

                                    @case('savings')
                                        Cuenta de ahorro
                                        @break

                                    @case('credit_line')
                                        Línea de crédito
                                        @break

                                    @default
                                        Otra

                                @endswitch
                            </td>

                            <td>
                                {{ $account->masked_number ?? '—' }}
                            </td>

                            <td>
                                {{ $account->currency }}
                            </td>

                            <td>
                                {{ number_format($account->transactions_count ?? 0, 0, ',', '.') }}
                            </td>

                            <td>
                                @if ($account->status === 'active')
                                    Activa
                                @else
                                    Inactiva
                                @endif
                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    @endif

</div>

@endsection
