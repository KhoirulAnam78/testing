@props(['title'])

<div class="card">
    <div class="card-header">
        <h5>{{ $title }}</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-nowrap align-middle">
                {{ $slot }}
            </table>
        </div>
    </div>
</div>
