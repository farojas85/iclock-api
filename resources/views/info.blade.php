@extends('layouts.app')

@section('title', 'Información del Dispositivo Biométrico')

@section('content')
<div class="container py-4">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-fingerprint me-2"></i> Información del Dispositivo Biométrico
            </h5>
            <a href="{{ url()->previous() }}" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
        <div class="card-body">
            @if(isset($data['ok']) && $data['ok'] == 1)
                <p class="text-success fw-semibold mb-4">
                    <i class="fas fa-check-circle me-1"></i>
                    {{ $data['mensaje'] }}
                </p>

                <table class="table table-striped table-hover align-middle">
                    <tbody>
                        @foreach($data['info'] as $key => $value)
                            <tr>
                                <th class="text-capitalize" style="width: 25%;">
                                    {{ str_replace('_', ' ', $key) }}
                                </th>
                                <td>
                                    @if(empty($value))
                                        <span class="text-muted fst-italic">No disponible</span>
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No se pudo obtener la información del dispositivo.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
