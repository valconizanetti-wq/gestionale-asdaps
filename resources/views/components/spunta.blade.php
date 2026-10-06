{{-- Casella di spunta con etichetta. <x-spunta name="x" label="..." :checked="$bool" /> --}}
@props(['name', 'label', 'checked' => false])
<label class="form-check">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) {{ $attributes }}>
    {{ $label }}
</label>
