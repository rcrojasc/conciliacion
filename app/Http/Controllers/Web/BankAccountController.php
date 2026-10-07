<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function create(): View
    {
        $banks = Bank::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('banking.accounts-create', [
            'banks' => $banks,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_id' => [
                'required',
                'string',
                'exists:banks,id',
            ],
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'account_type' => [
                'required',
                'string',
                'in:checking,sight,savings,credit_line,other',
            ],
            'currency' => [
                'required',
                'string',
                'in:CLP,USD,EUR,UF',
            ],
            'account_number' => [
                'required',
                'string',
                'min:4',
                'max:40',
                'regex:/^[0-9A-Za-z\-\.]+$/',
            ],
        ], [
            'bank_id.required' => 'Debe seleccionar un banco.',
            'bank_id.exists' => 'El banco seleccionado no es válido.',
            'name.required' => 'Debe indicar un nombre o alias para la cuenta.',
            'account_type.required' => 'Debe seleccionar el tipo de cuenta.',
            'account_type.in' => 'El tipo de cuenta seleccionado no es válido.',
            'currency.required' => 'Debe seleccionar la moneda.',
            'currency.in' => 'La moneda seleccionada no es válida.',
            'account_number.required' => 'Debe ingresar el número de cuenta.',
            'account_number.min' => 'El número de cuenta debe contener al menos 4 caracteres.',
            'account_number.regex' => 'El número de cuenta contiene caracteres no permitidos.',
        ]);

        $normalizedAccountNumber = strtoupper(
            preg_replace('/[\s\-\.]+/', '', $validated['account_number'])
        );

        $lastFour = Str::substr($normalizedAccountNumber, -4);

        $maskedNumber = '•••• ' . $lastFour;

        /*
         * El número completo ingresado por el usuario NO se persiste.
         *
         * external_id queda NULL hasta que exista una integración bancaria
         * que entregue un identificador externo seguro de la cuenta.
         */
        BankAccount::create([
            'bank_id' => $validated['bank_id'],
            'external_id' => null,
            'name' => trim($validated['name']),
            'account_type' => $validated['account_type'],
            'currency' => $validated['currency'],
            'masked_number' => $maskedNumber,
            'status' => 'active',
        ]);

        return redirect()
            ->route('banking.accounts')
            ->with('success', 'Cuenta bancaria registrada correctamente.');
    }
}
