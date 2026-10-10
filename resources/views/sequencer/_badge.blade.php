{{-- Status pill. Usage: @include('sequencer._badge', ['status' => $model->status]) (any enum with label() + badge()) --}}
<span class="badge badge-{{ $status->badge() }} light">{{ $status->label() }}</span>
