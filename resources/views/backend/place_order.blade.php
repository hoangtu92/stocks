<style>
    .level-3 {
        background-color: red;
    }

    .level-2 {
        background-color: yellow;
    }

    .level-1 {

    }
    th{
        text-transform: uppercase;
    }
    small{
        font-size: 9px
    }
    select{
        height: 30px;
        padding: 5px 20px;
    }
    label{
        width: 100%;
        display: block;
    }
</style>
<form action="{{ route("update_general_predict") }}" method="post">
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
                    <td>{{$td}}@if(preg_match("/range|rate/", $key))%@endif</td>
                @endforeach


            </tr>
        @endforeach

        </tbody>
    </table>
</form>

