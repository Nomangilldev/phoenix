<!-- resources/views/salereport.blade.php -->

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Sale Report</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        /* Hide the print button when printing */
        @media print {
            button {
                display: none;
            }
        }

        button {
            padding: 15px;
            margin: 50px;
            color: white;
            background-color: darkblue;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <button class="" onclick="window.print()">Print</button>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="row">
                    <div class="col-4">
                        <h2>Sale Report</h2>
                    </div>
                    <div class="col-8">
                        <div class="text-center mb-4">
                            {{-- <h4 style="margin-bottom: 10px;">Sale Report</h4> --}}
                            <div style="font-size: 18px; font-weight: bold;">
                                From: {{ $data['fromdate'] ?? 'N/A' }} &nbsp;&nbsp; | &nbsp;&nbsp; To:
                                {{ $data['todate'] ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    @php
                        $numberlist = $data['numberlist'];
                        unset($numberlist['00']); // Remove '00' from the number list
                        $chunks = array_chunk($numberlist, 33, true);

                        // Find the chunk index where '66' is located
                        $chunkIndex = 0;
                        foreach ($chunks as $index => $chunk) {
                            if (array_key_exists('66', $chunk)) {
                                $chunkIndex = $index;
                                break;
                            }
                        }

                        // Add '00' row to the same chunk as '66'
                        $chunks[$chunkIndex]['00'] = $data['numberlist']['00'];
                    @endphp

                    @foreach ($chunks as $chunk)
                        <div class="col-sm-4 col-md-4 col-xs-4" style="width:30%;float:left; font-size:30px">
                            <table border="1" class="table table-reponsive table-bordered table-hover">
                                @foreach ($chunk as $number => $amount)
                                    <tr>
                                        <th>{{ $number }}</th>
                                        <td>{{ $amount }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endforeach
                </div>

            </div>
            <div class="col-12" style="font-size: 30px;">
                <table class="table table-bordered table-hover">
                    <tr>
                        @php
                            $totalSoldInFraction = $data['totalSold'] * 20;
                            $winningTotalSoldInFraction = $data['winningNumbersTotal'] * 20;
                        @endphp
                        <th>Total Sold</th>
                        <td>{{ $data['totalSold'] }}</td>
                        <th>Total Sold(Fraction)</th>
                        <td>{{ $totalSoldInFraction }}</td>
                        <th>Commission</th>
                        <td>{{ $data['commission'] }}</td>
                    </tr>
                    <tr>
                        <th>Winnings</th>
                        <td>{{ $data['winnings'] }}</td>
                        <th>Balance</th>
                        <td>{{ $data['balance'] }}</td>
                    </tr>
                    <tr>
                        <th>Winning Numbers Total</th>
                        <td>{{ $data['winningNumbersTotal'] }}</td>
                        <th>Winning Numbers Total(Fraction)</th>
                        <td>{{ $winningTotalSoldInFraction }}</td>
                        <th>Lottery Name</th>
                        <td>{{ ucwords($data['lotteryName']) }}</td>
                    </tr>
                    <tr>
                        <th>Date</th>
                        <td>{{ $data['date'] }}</td>
                    </tr>
                </table>
                <table class="table table-bordered table-hover">
                    <tr>
                        <th>
                            @foreach ($data['users'] as $user)
                                {{ $user->username }} ({{ $user->user_role }}),
                            @endforeach
                        </th>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>

</html>
