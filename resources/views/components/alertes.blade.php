@if(session('succes'))
    <div class="mb-5 flex items-start gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-600/20" role="status">
        <x-icon name="check" class="mt-0.5 size-5 shrink-0"/> <span>{{ session('succes') }}</span>
    </div>
@endif
@if(session('erreur'))
    <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-600/20" role="alert">
        <x-icon name="alert" class="mt-0.5 size-5 shrink-0"/> <span>{{ session('erreur') }}</span>
    </div>
@endif
@if($errors->any() && ! old('_modale'))
    <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-600/20" role="alert">
        <x-icon name="alert" class="mt-0.5 size-5 shrink-0"/>
        <ul class="list-disc space-y-0.5 pl-4">
            @foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
        </ul>
    </div>
@endif
