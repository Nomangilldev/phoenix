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
            margin: 10px auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .header-info {
            font-size: 26px;
            /* Increased font size */
            width: 100%;
            border-collapse: collapse;
        }

        .header-info th,
        .header-info td {
            padding: 12px 10px;
            /* Increased padding for better spacing */
            text-align: left;
            vertical-align: top;
            font-weight: normal;
        }

        .header-info th {
            width: 30%;
            font-weight: normal;
            color: #333;
        }

        .header-info td {
            width: 70%;
            font-weight: bold;
            color: #000;
        }

        @media print {
            .header-info {
                font-size: 27px;
            }

            .header-info th,
            .header-info td {
                width: 50% !important;
                /* Force equal width */
            }

            .header {
                margin-bottom: 10px;
            }
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

        .qr-wrapper svg {
            width: 100%;
            height: auto;
            max-width: 300px;
            /* Optional max size */
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
            <table width=100% class="header-info">
                <tr>
                    <th>Fecha y hora:</th>
                    <td>{{ \Carbon\Carbon::parse($data['lotteryData']['adddatetime'])->format('d/m/Y h:i A') }}</td>
                </tr>
                <tr>
                    <th>Vendedor:</th>
                    <td>{{ isset($data['lotteryData']['seller']) ? ucwords($data['lotteryData']['seller']['username']) : 'N/A' }}
                    </td>
                </tr>
                <tr>
                    <th>Cliente:</th>
                    <td>{{ ucwords($data['lotteryData']['client_name']) }}</td>
                </tr>
                <tr>
                    <th>Número de billete:</th>
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
                    <td style="width:50%"> P:{{ number_format($grandTotalFrac, 2) }} |
                        Q:{{ number_format($grandTotalQuat, 2) }}</td>
                </tr>
                <tr>
                    <th style="width:50%">Estado de pago/ganancia</th>
                    <td style="width:50%">{{ $winNotWin }} | {{ $paidNotPaid }}</td>
                </tr>
            </table>

            <div class="qr-code" align="center">
                <div class="qr-wrapper">
                    {!! $data['qrCode'] !!}
                </div>
            </div>
        </div>
    </div>

</body>

</html>
