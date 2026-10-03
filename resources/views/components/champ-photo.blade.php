@props(['id' => 'photo', 'actuelle' => null])
{{-- Choix de la photo avec aperçu : on voit tout de suite qu'elle est bien chargée --}}
<div data-champ-photo>
    <span class="label">Photo <span class="font-normal text-slate-400">(optionnel)</span></span>
    <label for="{{ $id }}" data-photo-zone
           class="flex cursor-pointer items-center gap-4 rounded-xl border-2 border-dashed border-slate-200 px-4 py-4 text-sm text-slate-500 transition hover:border-brand-300 hover:bg-brand-50/40">
        <span class="relative inline-flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-slate-400">
            <img data-photo-apercu alt="Aperçu de la photo" class="{{ $actuelle ? '' : 'hidden' }} absolute inset-0 size-full object-cover" @if($actuelle) src="{{ $actuelle }}" @endif>
            <x-icon name="user-plus" class="size-7"/>
        </span>
        <span class="min-w-0 flex-1">
            <span data-photo-titre class="block font-semibold text-slate-700">{{ $actuelle ? 'Photo actuelle — cliquez pour la changer' : 'Télécharger une photo' }}</span>
            <span data-photo-detail class="block truncate text-xs">JPG, PNG ou WebP · 2 Mo max</span>
        </span>
        <span data-photo-ok class="hidden shrink-0 pill-green"><x-icon name="check" class="size-3.5"/> Photo chargée</span>
    </label>
    <input id="{{ $id }}" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
    <button type="button" data-photo-retirer class="mt-1 hidden text-xs font-semibold text-red-600 hover:underline">Retirer cette photo</button>
</div>
