<?php
require_once 'bootstrap.php';

use GuzzleHttp\Client;

$client = new Client();

$connectionParams = [
    'host' => DB_HOST,
    'dbname' => DB_DATABASE,
    'user' => DB_USERNAME,
    'password' => DB_PASSWORD,
    'driver' => 'pdo_mysql'
];

$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams);
$queryBuilder = $conn->createQueryBuilder();

$queryBuilder->select('*')->from('instrument')->where('instrument_type_id = 5');

$instruments = $queryBuilder->execute()->fetchAllAssociative();

$rows = [];

foreach ($instruments as $key => $instrument) {
    $metadata = json_decode($instrument['metadata'], true);
    $info = json_decode($instrument['info'], true);

    $logo = $metadata['Images'][4]['Uri'] ?? $metadata['Images'][3]['Uri'] ?? $metadata['Images'][2]['Uri'] ?? null;

    $rows[] = [
        'id'                                => $instrument['instrument_id'],
        'logo'                              => '<a href="https://www.etoro.com/markets/' . $instrument['symbol_full'] .'" target="_blank">' . ($logo ? '<img src="' . $logo . '" style="width:80px; height:80px;">' : 'Link') . '</a>',
        'symbol_full'                       => $instrument['symbol_full'],
        'instrument_id'                     => $instrument['instrument_id'],
        'instrument_type_id'                => $instrument['instrument_type_id'],
        'instrument_type_sub_category_id'   => $instrument['instrument_type_sub_category_id'],
        'instrument_display_name'           => $instrument['instrument_display_name'] . (!empty($info['companyName-TTM']) ? '<br />' . $info['companyName-TTM']  : '') . (!empty($info['website-TTM']) ? '<br /> <a href="' . $info['website-TTM'] . '" target="_blank">' . $info['website-TTM'] . '</a>': ''),
        'sector'                            => $info['sectorName-TTM'] ?? $info['sector-TTM'] ?? '',
        'industry'                          => $info['industryName-TTM'] ?? $info['industry-TTM'] ?? '',
        'short_bio'                         => ($info['shortBio-en-us'] ?? ''),
        'long_bio'                          => ($info['longBio-en-us'] ?? $info['shortBio-en-us'] ?? ''),
        'next_earning_date'                 => isset($info['nextEarningDate']) ? date("Y-m-d", strtotime($info['nextEarningDate'])) : '',
        'companyName-TTM'                   => $info['companyName-TTM'] ?? '',
        'exchange'                          => $metadata['PriceSource'] ?? '',
        'next_dividend_ex_date'             => isset($info['dividendExDate-TTM']) ? date("Y-m-d", strtotime($info['dividendExDate-TTM'])) : '',
        'next_earning_date'                 => isset($info['nextEarningDate']) ? date("Y-m-d", strtotime($info['nextEarningDate'])) : '',
        'company_founded_date'              => $info['companyFoundedDate-TTM'] ?? '',


    ];
//    print_r($info);
//    print_r($metadata);
//    exit;

//    if ($key > 5) break;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>eToro</title>
    <meta name="description" content="eToro">
    <meta name="author" content="eToro">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="css/fixedHeader.dataTables.min.css">
    <link rel="stylesheet" href="css/style.css">

    <script type="text/javascript" language="javascript" src="js/jquery-3.5.1.js"></script>
    <script type="text/javascript" language="javascript" src="js/dataTables.bootstrap4.min.js"></script>
    <script type="text/javascript" language="javascript" src="js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" language="javascript" src="js/dataTables.fixedHeader.min.js"></script>
    <script>
        $(document).ready(function() {
            let dataset = <?php echo json_encode($rows)?>;

            // Setup - add a text input to each footer cell
            $('#instrument-table thead tr').clone(true).appendTo( '#instrument-table thead' );
            $('#instrument-table thead tr:eq(1) th').each( function (i) {
                var title = $(this).text();

                switch (title) {
                    case 'Id':
                    case 'Logo':
                        $(this).html( '' );
                        break;
                    case 'Code':
                        $(this).html( '<input type="text" class="code" placeholder="Search '+title+'" />' );
                        break;
                    default:
                        $(this).html( '<input type="text" placeholder="Search '+title+'" />' );
                        break;
                }

                $( 'input', this ).on( 'keyup change', function () {
                    if ( table.column(i).search() !== this.value ) {
                        table
                            .column(i)
                            .search( this.value )
                            .draw();
                    }
                } );
            } );

            let table = $('#instrument-table').DataTable( {
                "lengthMenu": [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, "All"]],
                "data": dataset,
                "columns": [
                    { "data": "id" },
                    { "data": "logo" },
                    { "data": "symbol_full" },
                    { "data": "instrument_display_name" },
                    { "data": "sector" },
                    { "data": "industry" },
                    { "data": "exchange" },
                    { "data": "next_earning_date" },
                    { "data": "next_dividend_ex_date" },
                    { "data": "company_founded_date" },
                    { "data": "short_bio" },
                    { "data": "long_bio" }
                ],
                "columnDefs": [
                    { "width": "50px", "targets": 0 },
                    { "width": "50px", "targets": 1 },
                    { "width": "50px", "targets": 2 },
                ],
                "order": [[1, 'asc']]
            } );

            $('a.toggle-vis').on( 'click', function (e) {
                e.preventDefault();

                // Get the column API object
                var column = table.column( $(this).attr('data-column') );

                // Toggle the visibility
                column.visible( ! column.visible() );
            } );
        });
    </script>
</head>
<body>
    <div id="instrument-table-container">
        <div class="btn-group">
            Show / Hide Columns :
            <a class="toggle-vis" data-column="0">Name</a> |
            <a class="toggle-vis" data-column="1">Logo</a> |
            <a class="toggle-vis" data-column="2">Code</a> |
            <a class="toggle-vis" data-column="3">Name</a> |
            <a class="toggle-vis" data-column="4">Sector</a> |
            <a class="toggle-vis" data-column="5">Industry</a> |
            <a class="toggle-vis" data-column="6">Exchange</a> |
            <a class="toggle-vis" data-column="7">Next Earnings</a> |
            <a class="toggle-vis" data-column="8">Next Dividend</a> |
            <a class="toggle-vis" data-column="9">Company Founded</a> |
            <a class="toggle-vis" data-column="10">Short Bio</a>
            <a class="toggle-vis" data-column="11">Long Bio</a>
        </div>

        <table id="instrument-table" class="table table-striped table-bordered" style="width:100%">
            <thead>
            <tr>
                <th>Id</th>
                <th>Logo</th>
                <th>Code</th>
                <th>Name</th>
                <th>Sector</th>
                <th>Industry</th>
                <th>Exchange</th>
                <th>Next Earnings</th>
                <th>Next Dividend</th>
                <th>Company Founded</th>
                <th>Short Bio</th>
                <th>Long Bio</th>
            </tr>
            </thead>
            <tfoot>
            <tr>
                <th>Id</th>
                <th>Logo</th>
                <th>Code</th>
                <th>Name</th>
                <th>Sector</th>
                <th>Industry</th>
                <th>Exchange</th>
                <th>Next Earnings</th>
                <th>Next Dividend</th>
                <th>Company Founded</th>
                <th>Short Bio</th>
                <th>Long Bio</th>
            </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>


