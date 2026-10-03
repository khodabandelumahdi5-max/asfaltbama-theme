@php($makes = $makes ?? \App\Http\Controllers\ShopController::vehicleOptions())
<select name="{{ $name }}" @if($required ?? false) required @endif @if($submitOnChange ?? false) onchange="this.form.submit()" @endif aria-label="انتخاب خودرو">
    <option value="">— برند، مدل و سال را انتخاب کنید —</option>
    @foreach($makes->groupBy('origin') as $origin => $group)
        @foreach($group as $make)
            <optgroup label="{{ $make->name }} ({{ \App\Models\CarMake::ORIGINS[$origin] }})">
                @foreach($make->vehicles as $v)
                    @php($v->setRelation('make', $make))
                    <option value="{{ $v->id }}" @selected((int) $selected === $v->id)>{{ $v->label() }}</option>
                @endforeach
            </optgroup>
        @endforeach
    @endforeach
</select>
