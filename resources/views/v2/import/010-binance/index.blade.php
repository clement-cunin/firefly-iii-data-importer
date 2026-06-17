@extends('layout.v2')
@section('content')
    <div class="container">
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>Binance — Select symbols</h1>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">Trading pairs to import</div>
                    <div class="card-body">
                        <p>Enter the Binance trading pairs you want to import, separated by commas. Examples: <code>BTCEUR, ETHEUR, BNBEUR</code></p>
                        <p class="text-muted">Only spot trades are imported. Each trade creates one transaction in Firefly III using the quote currency amount.</p>

                        @if('' !== ($error ?? ''))
                            <p class="text-danger">{{ $error }}</p>
                        @endif

                        <form method="post" action="{{ route('binance-connect.post', [$identifier]) }}" accept-charset="UTF-8">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}"/>

                            <div class="form-group row mb-3">
                                <label for="symbols" class="col-sm-3 col-form-label">Trading pairs</label>
                                <div class="col-sm-9">
                                    <input type="text"
                                           name="symbols"
                                           id="symbols"
                                           class="form-control"
                                           placeholder="BTCEUR,ETHEUR,BNBEUR"
                                           value="{{ $symbols }}"
                                           aria-describedby="symbols_help">
                                    <small id="symbols_help" class="form-text text-muted">
                                        Comma-separated list of Binance trading pairs (e.g. BTCEUR, ETHEUR). Must match the symbols on Binance exactly.
                                    </small>
                                </div>
                            </div>

                            <button type="submit" class="float-end btn btn-primary">Continue &rarr;</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-body">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('configure-import.index', [$identifier]) }}" class="btn btn-secondary">
                                <span class="fas fa-arrow-left"></span> Go back to configuration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
