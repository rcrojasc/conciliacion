@extends('layouts.app')
@section('content')
<h1>Historial de conciliaciones</h1>
<div class="card"><table><thead><tr><th>Fecha</th><th>Método</th><th>Estado</th><th>Score</th><th>Ítems</th><th>Acción</th></tr></thead><tbody>
@foreach($reconciliations as $r)<tr><td>{{ $r->created_at }}</td><td>{{ $r->method }}</td><td>{{ $r->status->value ?? $r->status }}</td><td>{{ $r->confidence_score }}%</td><td>{{ $r->items->count() }}</td><td>@if(($r->status->value ?? $r->status)==='approved' && $r->method!=='reversal')<form method="POST" action="{{ route('reconciliation.reverse',$r) }}">@csrf<input name="reason" placeholder="Motivo de reversa" required minlength="5"><button type="submit">Reversar</button></form>@endif</td></tr>@endforeach
</tbody></table>{{ $reconciliations->links() }}</div>
@endsection
