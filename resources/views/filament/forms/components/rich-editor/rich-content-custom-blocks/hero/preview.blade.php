<div>
    <ul>
    @foreach ($items as $item)
       <li>
        @if($item["is_completed"]) ✅
        @else 🔲
        @endif

        {{$item["task"] }}
    </li>
    @endforeach
    </ul>
</div>
