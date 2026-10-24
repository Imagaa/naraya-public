@props(['headers' => []])

<div style="overflow-x:auto">
    <table {{ $attributes->merge(['class' => 'table']) }}>
        @if(count($headers))
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
