<?php

namespace ccxt;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use \ccxt\ExchangeError;
use \ccxt\ArgumentsRequired;
use \ccxt\OrderNotFound;

class coineal extends Exchange {

    public function describe() {
        return $this->deep_extend(parent::describe (), array(
            'id' => 'coineal',
            'name' => 'Coineal',
            'countries' => ['US'],
            'version' => 'v1',
            'rateLimit' => 1000,
            'has' => array(
                'createMarketOrder' => false,
                'fetchOrder' => true,
                'fetchOrders' => true,
                'fetchOpenOrders' => true,
                'fetchCurrencies' => false,
                'fetchTicker' => true,
                'fetchTickers' => false,
                'fetchOHLCV' => false,
                'fetchOrderBook' => true,
                'fetchTrades' => false,
            ),
            'urls' => array(
                'api' => array(
                    'public' => 'https://exchange-open-api.coineal.com/open/api',
                    'private' => 'https://exchange-open-api.coineal.com/open/api',
                ),
                'www' => 'https://www.coineal.com',
                'doc' => array(
                    'https://www.coineal.com/static-page/api/en_US/api.html',
                ),
                'fees' => '',
            ),
            'api' => array(
                'public' => array(
                    'get' => array(
                        'get_ticker',
                        'market_dept',
                        'common/symbols',
                    ),
                ),
                'private' => array(
                    'get' => array(
                        'account/info',
                    ),
                ),
            ),
            'fees' => array(
                'trading' => array(
                    'maker' => 0.09,
                    'taker' => 0.12,
                ),
            ),
        ));
    }

    public function fetch_markets($params = array ()) {
        // array(
        //     "$symbol" => "btcusdt",
        //     "count_coin" => "usdt",
        //     "amount_precision" => 5,
        //     "base_coin" => "btc",
        //     "price_precision" => 2
        // ),
        $response = $this->publicGetCommonSymbols ($params);
        $result = array();
        for ($i = 0; $i < count($response['data']); $i++) {
            $market = $response['data'][$i];
            $baseId = $this->safe_string($market, 'base_coin');
            $quoteId = $this->safe_string($market, 'count_coin');
            $base = $this->safe_currency_code($baseId);
            $quote = $this->safe_currency_code($quoteId);
            $id = $base . $quote;
            $symbol = $base . $quote;
            $precision = array(
                'amount' => $this->safe_integer($market, 'amount_precision'),
                'price' => $this->safe_integer($market, 'price_precision'),
            );
            $result[] = array(
                'id' => strtolower($id),
                'symbol' => strtolower($symbol),
                'base' => $base,
                'quote' => $quote,
                'baseId' => $baseId,
                'quoteId' => $quoteId,
                'active' => true,
                'precision' => $precision,
                'limits' => array(
                    'amount' => array(
                        'min' => null,
                        'max' => null,
                    ),
                    'price' => array(
                        'min' => pow(10, -$precision['price']),
                        'max' => null,
                    ),
                    'cost' => array(
                        'min' => null,
                        'max' => null,
                    ),
                ),
                'info' => $market,
            );
        }
        return $result;
    }

    public function fetch_ticker($symbol, $params = array ()) {
        $this->load_markets();
        $timestamp = $this->milliseconds();
        $request = array_merge(array(
            'symbol' => $symbol,
        ), $params);
        $response = $this->publicGetGetTicker ($request);
        $ticker = $this->safe_value($response, 'data');
        return array(
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'high' => $this->safe_float($ticker, 'high'),
            'low' => $this->safe_float($ticker, 'low'),
            'bid' => $this->safe_float($ticker, 'buy'),
            'bidVolume' => null,
            'ask' => $this->safe_float($ticker, 'sell'),
            'askVolume' => null,
            'vwap' => null,
            'previousClose' => null,
            'open' => null,
            'close' => null,
            'last' => $this->safe_float($ticker, 'last'),
            'percentage' => null,
            'change' => null,
            'average' => null,
            'baseVolume' => $this->safe_float($ticker, 'vol'),
            'quoteVolume' => null,
            'info' => $ticker,
        );
    }

    public function fetch_order_book($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $request = array(
            'symbol' => $this->market_id($symbol),
            'type' => 'step0',
        );
        if ($limit !== null) {
            $request['size'] = $limit;
        }
        $response = $this->publicGetMarketDept (array_merge($request, $params));
        $data = $this->safe_value($response, 'data');
        return $this->parse_order_book($data->tick, $data->tick.time, 'bids', 'asks');
    }

    public function fetch_balance($params = array ()) {
        $this->load_markets();
        $query = $this->omit($params, 'type');
        $response = $this->privateGetWalletBalance ($query);
        $balances = $this->safe_value($response, 'data');
        $wallets = $this->safe_value($balances, 'WALLET');
        $result = array( 'info' => $wallets );
        for ($i = 0; $i < count($wallets); $i++) {
            $wallet = $wallets[$i];
            $currencyId = $wallet['coinType'];
            $code = $this->safe_currency_code($currencyId);
            $account = $this->account();
            $account['free'] = $this->safe_float($wallet, 'available');
            $account['total'] = $this->safe_float($wallet, 'total');
            $result[$code] = $account;
        }
        return $this->parse_balance($result);
    }

    public function create_order($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market($symbol);
        $method = 'privatePostTradeOrderCreate';
        $direction = $side === 'buy' ? 'BID' : 'ASK';
        $request = array(
            'amount' => $this->amount_to_precision($symbol, $amount),
            'direction' => $direction,
            'pair' => $this->safe_string($market, 'id'),
            'price' => $this->price_to_precision($symbol, $price),
        );
        $response = $this->$method (array_merge($request, $params));
        return array(
            'id' => $this->safe_value($response, 'data'),
            'info' => $response,
        );
    }

    public function cancel_order($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array(
            'orderNo' => $id,
            'pair' => $this->market_id($symbol),
        );
        return $this->privatePostTradeOrderCancel (array_merge($request, $params));
    }

    public function fetch_open_orders($symbol = null, $since = null, $limit = null, $params = array ()) {
        if ($symbol === null) {
            throw new ArgumentsRequired($this->id . ' fetchOrders requires a $symbol argument');
        }
        $this->load_markets();
        $market = $this->market($symbol);
        $request = array(
            'pair' => $this->safe_string($market, 'id'),
        );
        if ($limit !== null) {
            $request['size'] = $limit;
        }
        $response = $this->privateGetTradeOrderListUnfinished (array_merge($request, $params));
        $result = $this->safe_value($response, 'data');
        return $this->parse_orders($this->safe_value($result, 'data'), $market, $since, $limit);
    }

    public function fetch_order($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array(
            'orderNo' => $id,
            'pair' => $this->market_id($symbol),
        );
        $response = $this->privateGetTradeOrderUnfinishedDetail (array_merge($request, $params));
        $data = $this->safe_value($response, 'data');
        if (!$data) {
            throw new OrderNotFound($this->id . ' order ' . $id . ' not found');
        }
        return $this->parse_order($data);
    }

    public function parse_order($order, $market = null) {
        $marketName = $this->safe_string($order, 'pair');
        $market = $market || $this->findMarket ($marketName);
        $timestamp = $this->safe_string($order, 'createdTime');
        if ($timestamp !== null) {
            $timestamp = (int) round(floatval ($timestamp) * 1000);
        }
        $direction = $this->safe_value($order, 'direction');
        $side = $direction === 'BID' ? 'BUY' : 'SELL';
        $amount = $this->safe_float($order, 'totalAmount');
        $fillAmount = $this->safe_float($order, 'dealAmount', $amount);
        $remaining = $amount - $fillAmount;
        return array(
            'id' => $this->safe_string($order, 'id'),
            'datetime' => $this->iso8601($timestamp),
            'timestamp' => $timestamp,
            'lastTradeTimestamp' => null,
            'status' => null,
            'symbol' => $this->safe_string($market, 'symbol'),
            'side' => $side,
            'type' => $this->safe_string($order, 'orderType'),
            'price' => $this->safe_float($order, 'price'),
            'cost' => null,
            'amount' => $amount,
            'filled' => $fillAmount,
            'remaining' => $remaining,
            'fee' => null,
            'info' => $order,
        );
    }

    public function sign($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'][$api] . '/' . $this->implode_params($path, $params);
        $query = $this->omit($params, $this->extract_params($path));
        if ($method === 'GET') {
            if ($query) {
                $url .= '?' . $this->urlencode($query);
            }
        }
        if ($api === 'private') {
            $this->check_required_credentials();
            $query = $this->urlencode($query);
            if ($method === 'POST') {
                $body = $query;
            }
            $secret = $this->encode($this->secret);
            $signature = $this->hmac($query, $secret, 'sha256');
            $headers = array(
                'Cache-Control' => 'no-cache',
                'Content-type' => 'application/x-www-form-urlencoded',
                'X_ACCESS_KEY' => $this->apiKey,
                'X_SIGNATURE' => $signature,
            );
        }
        return array( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function handle_errors($code, $reason, $url, $method, $headers, $body, $response, $requestHeaders, $requestBody) {
        $httpCode = $this->safe_integer($response, 'code', 200);
        if ($response === null) {
            return;
        }
        if ($code >= 400) {
            throw new ExchangeError($this->id . ' HTTP Error ' . $code . ' $reason => ' . $reason);
        }
        if ($httpCode >= 400) {
            $message = $this->safe_value($response, 'msg', '');
            throw new ExchangeError($this->id . ' HTTP Error ' . $httpCode . ' $message => ' . $message);
        }
    }
}
