@extends("app")

@section("content")
<form action="{{ route("update_final_predict") }}" method="post">
    @csrf
    <table border='1' cellpadding='5' cellspacing='0'>
        <thead>
        <tr>

            @foreach($data[array_keys($data)[0]] as $key => $value)
                @if(!isset($header[$key])) @continue @endif
                <th>{{$header[$key]}}<br><small>{{$key}}</small></th>
            @endforeach

        </tr>
        </thead>
        <tbody>
        @foreach($data as $tr)
            <tr>

                @foreach($tr as $key=> $td)
                    @if(!isset($header[$key])) @continue @endif
                    <td>@if($key == "predict_final") <input type="number" class="ajax-field" name="predict_final[{{$tr->date}}]" value="{{$td}}">
                        @elseif($key == "custom_general_predict")
                            <select class="ajax-field" name="custom_general_predict[{{$tr->date}}]">
                                <option value="" @if($td == null) selected @endif>Auto</option>
                                <option value="-1" @if($td == "-1") selected @endif>跌</option>
                                <option value="1" @if($td == "1") selected @endif>漲</option>
                            </select>
                        @elseif($key == "predict_BK")
                            @if($td < 0) 跌 @else 漲 @endif
                        @else
                        {{$td}}
                        @endif
                        @if(preg_match("/range|rate/", $key))%@endif</td>
                @endforeach


            </tr>
        @endforeach

        </tbody>
    </table>
</form>
<script>
    @if(\Session::has('success'))
    alert("{{\Session::get("success")}}");
    @endif

    window.onload = function () {
        let ajaxField = document.querySelectorAll(".ajax-field");
        ajaxField.forEach(function () {
            this.onchange = function (e) {
                e.target.form.submit();
            }
        })
    }
</script>

@endsection
