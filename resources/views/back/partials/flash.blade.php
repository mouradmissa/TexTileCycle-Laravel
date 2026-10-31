@if(session('success'))
    <div class="flash flash-success" role="status">{{ session('success') }}</div>
@endif

@if(session('info'))
    <div class="flash flash-success" role="status">{{ session('info') }}</div>
@endif

@if($errors->any())
    <div class="flash flash-error" role="alert">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
