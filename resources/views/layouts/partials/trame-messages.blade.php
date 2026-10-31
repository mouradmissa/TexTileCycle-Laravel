@if(session('success') || session('info') || session('error') || $errors->any())
    <div class="tr-messages">
        @if(session('success'))
            <p class="tr-message" role="status">{{ session('success') }}</p>
        @endif
        @if(session('info'))
            <p class="tr-message est-info" role="status">{{ session('info') }}</p>
        @endif
        @if(session('error') || $errors->any())
            <div role="alert">
                @if(session('error'))
                    <p class="tr-message est-erreur">{{ session('error') }}</p>
                @endif
                @foreach($errors->all() as $error)
                    <p class="tr-message est-erreur">{{ $error }}</p>
                @endforeach
            </div>
        @endif
    </div>
@endif
