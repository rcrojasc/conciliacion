@extends('layouts.app')

@section('title', 'Nueva cuenta bancaria · ARIONS FINANCE')

@section('content')

<div class="page-head">
    <div>
        <h1>Nueva cuenta bancaria</h1>
        <p>
            Registra una cuenta bancaria para la organización activa.
        </p>
    </div>

    <div>
        <a href="{{ route('banking.accounts') }}">
            ← Volver a cuentas
        </a>
    </div>
</div>

@if ($errors->any())
    <div class="card" style="margin-bottom: 20px;">
        <strong>No fue posible registrar la cuenta.</strong>

        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">

    <form
        method="POST"
        action="{{ route('banking.accounts.store') }}"
        autocomplete="off"
    >
        @csrf

        <div style="margin-bottom: 20px;">
            <label for="bank_id">
                Banco
            </label>

            <select
                id="bank_id"
                name="bank_id"
                required
                style="width: 100%;"
            >
                <option value="">
                    Seleccione un banco
                </option>

                @foreach ($banks as $bank)
                    <option
                        value="{{ $bank->id }}"
                        @selected(old('bank_id') === $bank->id)
                    >
                        {{ $bank->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="name">
                Nombre o alias
            </label>

            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                maxlength="120"
                placeholder="Ej.: Cuenta corriente principal"
                required
                style="width: 100%;"
            >
        </div>

        <div style="margin-bottom: 20px;">
            <label for="account_type">
                Tipo de cuenta
            </label>

            <select
                id="account_type"
                name="account_type"
                required
                style="width: 100%;"
            >
                <option value="">
                    Seleccione el tipo
                </option>

                <option
                    value="checking"
                    @selected(old('account_type') === 'checking')
                >
                    Cuenta corriente
                </option>

                <option
                    value="sight"
                    @selected(old('account_type') === 'sight')
                >
                    Cuenta vista
                </option>

                <option
                    value="savings"
                    @selected(old('account_type') === 'savings')
                >
                    Cuenta de ahorro
                </option>

                <option
                    value="credit_line"
                    @selected(old('account_type') === 'credit_line')
                >
                    Línea de crédito
                </option>

                <option
                    value="other"
                    @selected(old('account_type') === 'other')
                >
                    Otra
                </option>
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="currency">
                Moneda
            </label>

            <select
                id="currency"
                name="currency"
                required
                style="width: 100%;"
            >
                <option
                    value="CLP"
                    @selected(old('currency', 'CLP') === 'CLP')
                >
                    CLP — Peso chileno
                </option>

                <option
                    value="USD"
                    @selected(old('currency') === 'USD')
                >
                    USD — Dólar estadounidense
                </option>

                <option
                    value="EUR"
                    @selected(old('currency') === 'EUR')
                >
                    EUR — Euro
                </option>

                <option
                    value="UF"
                    @selected(old('currency') === 'UF')
                >
                    UF — Unidad de Fomento
                </option>
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="account_number">
                Número de cuenta
            </label>

            <input
                id="account_number"
                type="text"
                name="account_number"
                value="{{ old('account_number') }}"
                maxlength="40"
                inputmode="numeric"
                autocomplete="off"
                placeholder="Ingrese el número de cuenta"
                required
                style="width: 100%;"
            >

            <small>
                Por seguridad, ARIONS FINANCE no almacenará el número
                completo. Solo se conservarán los últimos cuatro caracteres.
            </small>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="submit">
                Guardar cuenta
            </button>

            <a href="{{ route('banking.accounts') }}">
                Cancelar
            </a>
        </div>

    </form>

</div>

@endsection
