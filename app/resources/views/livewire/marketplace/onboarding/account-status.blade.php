<div class="page">
    <main class="container pg onboard">
        <section class="panel onboard-card" aria-labelledby="h-status">
            @if($rejected)
                <span class="tile"><x-lucide-circle-x class="ico" /></span>
                <h1 id="h-status">Pendaftaran tidak disetujui</h1>
                @if($reason)
                    <p class="note sale"><x-lucide-circle-alert class="ico" /><span>{{ $reason }}</span></p>
                @endif
                <p>Jika menurut Anda ini keliru, hubungi kami dan sertakan nama toko Anda.</p>
                <a class="btn btn-primary" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener"><x-lucide-message-circle class="ico" />Hubungi kami</a>
            @else
                <span class="tile"><x-lucide-clock class="ico" /></span>
                <h1 id="h-status">Akun Anda sedang ditinjau</h1>
                <p>Kami akan mengabari begitu disetujui. Biasanya kurang dari 1 hari kerja.</p>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Keluar</button>
            </form>
        </section>
    </main>
</div>
