@if(session('status'))
    <div class="alert alert-ok">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-bad">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif
