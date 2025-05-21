<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lotteries Diarias</title>
    <style type="text/css">
        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: sans-serif;
            font-size: 36px;
        }

        .container {
            width: 90%;
            margin: 20px auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-info {
            font-size: 30px;
        }

        .header-info th {
            text-align: left;
        }

        .logo img {
            width: 150px;
        }
        .logo {
            text-align: center;
        }
        .table-title {
            text-align: left;
            /* font-size: 18px; */
            font-weight: bold;
            margin-top: 15px;
        }

        .table1 {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table1,
        .table1 td,
        .table1 th {
            border: 1px solid black;
        }

        .table1 th,
        .table1 td {
            padding: 10px;
            text-align: center;
        }

        .footer-text {
            text-align: center;
            font-weight: bold;
            margin-top: 20px;
            font-size: 14px;
        }

        /* Hide the print button when printing */
        @media print {
            button {
                display: none;
            }
        }

        button {
            padding: 10px 20px;
            color: white;
            background-color: darkblue;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            margin: 20px auto;
            display: block;
        }
    </style>
</head>

<body>
    <!--<button onclick="window.print()">Print</button>-->

    <div class="container">
        <!-- Header Section -->
        <div class="logo">
            <h1 style="color:darkgoldenrod; font-family:serif; font:bold">Fenix F</h1>
        </div>
        <div class="header">
            <table width="100%" class="header-info">
                <tr>
                    <th width='70%'>Fecha y hora:</th>
                    <td>{{ \Carbon\Carbon::parse($data['lotteryData']['adddatetime'])->format('d/m/Y h:i A') }}</td>
                </tr>
                <tr>
                    <th width='70%'>Vendedor:</th>
                    <td>{{ isset($data['lotteryData']['seller']) ? ucwords($data['lotteryData']['seller']['username']) : 'N/A' }}
                    </td>
                </tr>
                <tr>
                    <th width='70%'>Cliente:</th>
                    <td>{{ ucwords($data['lotteryData']['client_name']) }}</td>
                </tr>
                <tr>
                    <th width='70%'>Número de billete:</th>
                    <td>{{ ucwords($data['lotteryData']['order_id']) }}</td>
                </tr>
            </table>
        </div>
        @php
            $groupedItems = collect($data['lotteryData']['order_items'])->groupBy('product_id');
            $grandTotalFrac = 0;
            $grandTotalQuat = 0;
            $totalWinningAmount = 0;
        @endphp
        <!-- Lottery Table Section -->
        <div>
            @foreach ($groupedItems as $productId => $items)
                <!-- Display product name only once per product group -->
                <div class="table-title">{{ $items->first()['product_name'] }}</div>

                <!-- Table for items under this product name -->
                <table class="table1">
                    <tr>
                        <th style="width:50%">Numero</th>
                        <th style="width:50%">Pedazos</th>
                    </tr>
                    @php
                        $total = 0;
                        // Initialize paid status to "Not Paid"
                        $paidNotPaid = 'No pagado';
                    @endphp
                    @foreach ($items as $item)
                        @php
                            // Check if transaction_paid_id is greater than 0 for any item
                            if ($item['transaction_paid_id'] > 0 && $item['winning_amount'] > 0) {
                                $paidNotPaid = 'Pagado';
                            }
                        @endphp
                        <tr style="background-color: {{ $item['winning_amount'] > 0 ? '#ffcccc' : 'transparent' }};">
                            <td>{{ $item['lot_number'] }}</td>
                            <td>{{ $item['lot_frac'] }}</td>
                        </tr>
                        @php
                            $total += $item['lot_frac'];
                            $totalWinningAmount += $item['winning_amount'];

                        @endphp
                    @endforeach
                    @php
                        $totalQuator = $total * 0.05;
                        $winNotWin = $totalWinningAmount > 0 ? 'Ganador' : 'No ganador';
                    @endphp
                    <tr>
                        <th>Total</th>
                        <th> P:{{ number_format($total, 2) }} | Q:{{ number_format($totalQuator, 2) }}</th>
                    </tr>
                </table>
                @php
                    $grandTotalFrac += $total;
                    $grandTotalQuat += $totalQuator;
                @endphp
            @endforeach

            <table class="table1">
                <tr>
                    <th style="width:50%">Grand total</th>
                    <th style="width:50%"> $:{{ number_format($grandTotalFrac, 2) }} |
                        Q:{{ number_format($grandTotalQuat, 2) }}</th>
                </tr>
                <tr>
                    <th style="width:50%">Estado de pago/ganancia</th>
                    <th style="width:50%">{{ $winNotWin }} | {{ $paidNotPaid }}</th>
                </tr>
            </table>

            <div class="qr-code" align="center">
                {!! $data['qrCode'] !!}
            </div>
        </div>
    </div>

</body>

</html>
