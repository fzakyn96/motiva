@php
    $render = $render ?? null;
    $align = $align ?? 'center';
    $emptyHeight = $emptyHeight ?? '50px';
@endphp

@if($render && !empty($render['signed']))
    <div style="text-align: {{ $align }}; margin: 6px 0; min-height: {{ $emptyHeight }};">
        @if(!empty($render['show_visual']) && !empty($render['visual_uri']) && !empty($render['show_qr']) && !empty($render['qr_uri']))
            <table style="border: none; border-collapse: collapse; {{ $align === 'center' ? 'margin: 0 auto;' : '' }}">
                <tr>
                    <td style="border: none; padding: 0 6px 0 0; vertical-align: middle;">
                        <img src="{{ $render['visual_uri'] }}" alt="Tanda Tangan" style="max-height: 52px; max-width: 140px;">
                    </td>
                    <td style="border: none; padding: 0 0 0 6px; vertical-align: middle;">
                        <img src="{{ $render['qr_uri'] }}" alt="QR Verifikasi" style="height: 54px; width: 54px;">
                    </td>
                </tr>
            </table>
        @elseif(!empty($render['show_qr']) && !empty($render['qr_uri']))
            <div>
                <img src="{{ $render['qr_uri'] }}" alt="QR Verifikasi" style="height: 58px; width: 58px;">
            </div>
        @elseif(!empty($render['show_visual']) && !empty($render['visual_uri']))
            <div>
                <img src="{{ $render['visual_uri'] }}" alt="Tanda Tangan" style="max-height: 55px; max-width: 165px;">
            </div>
        @else
            <div style="height: {{ $emptyHeight }};"></div>
        @endif
    </div>
@else
    <div style="height: {{ $emptyHeight }};"></div>
@endif

