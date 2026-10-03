{{-- Moyen de paiement + référence. Variable : $prefixe --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="{{ $prefixe }}-mode" class="label">Moyen de paiement</label>
        <select id="{{ $prefixe }}-mode" name="mode" required class="input">
            @foreach(\App\Models\Paiement::MODES as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected(old('mode', 'especes') === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $prefixe }}-reference" class="label">Référence <span class="font-normal text-slate-400">(mobile money / carte)</span></label>
        <input id="{{ $prefixe }}-reference" type="text" name="reference" value="{{ old('reference') }}" maxlength="100" class="input" placeholder="ex. MP2410.1234.A567">
    </div>
</div>
