{{-- Modes de paiement en pastilles. Variable : $prefixe --}}
<fieldset>
    <legend class="label">Mode de paiement</legend>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach(\App\Models\Paiement::MODES as $valeur => $libelle)
            <label class="choice items-center py-2.5 text-center text-sm font-semibold text-slate-700">
                <input type="radio" name="mode" value="{{ $valeur }}" required @checked(old('mode', 'especes') === $valeur)>
                {{ $libelle }}
            </label>
        @endforeach
    </div>
</fieldset>
<div>
    <label for="{{ $prefixe }}-reference" class="label">Référence de transaction <span class="font-normal text-slate-400">(mobile money / carte)</span></label>
    <input id="{{ $prefixe }}-reference" type="text" name="reference" value="{{ old('reference') }}" maxlength="100" class="input" placeholder="ex. MP240930.1234.A56789">
</div>
