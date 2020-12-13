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

foreach ($instruments as $instrument) {
    $rows[] = [
        'id'                                => $instrument['instrument_id'],
        'instrument_id'                     => $instrument['instrument_id'],
        'instrument_type_id'                => $instrument['instrument_type_id'],
        'instrument_type_sub_category_id'   => $instrument['instrument_type_sub_category_id'],
        'instrument_display_name'           => $instrument['instrument_display_name'],
        'stocks_industry_id'                => $instrument['stocks_industry_id'],
        'exchange_id'                       => $instrument['exchange_id'],
        'symbol_full'                       => $instrument['symbol_full'],
        'has_info'                          => $instrument['has_info'],
    ];

    $metadata = json_decode($instrument['metadata'], true);
    $info = json_decode($instrument['info'], true);
    var_dump($instrument, $metadata, $info); exit;
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
    <link rel="stylesheet" href="css/style.css">

    <script type="text/javascript" language="javascript" src="js/jquery-3.5.1.js"></script>
    <script type="text/javascript" language="javascript" src="js/dataTables.bootstrap4.min.js"></script>
    <script type="text/javascript" language="javascript" src="js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            function format ( d ) {
                return '' +
                    '<div style="float: left"><img src="https://etoro-cdn.etorostatic.com/market-avatars/nzd-usd/50x50.png"</div>' +
                    '' +
                    '<tabl estyle="float: right" cellpadding="5" cellspacing="0" border="0" style="padding-left:50px;">'+
                    '<tr>'+
                    '<td>Full name:</td>'+
                    '<td>'+d.instrument_display_name+'</td>'+
                    '</tr>'+
                    '<tr>'+
                    '<td>Extension number:</td>'+
                    '<td>'+d.instrument_display_name+'</td>'+
                    '</tr>'+
                    '<tr>'+
                    '<td>Extra info:</td>'+
                    '<td>And any further details here (images etc)...</td>'+
                    '</tr>'+
                    '</table>';
            }

            let dataset = <?php echo json_encode($rows)?>;

            let table = $('#instrument-table').DataTable( {
                "data": dataset,
                "columns": [
                    {
                        "className":      'details-control',
                        "orderable":      false,
                        "data":           null,
                        "defaultContent": ''
                    },
                    { "data": "instrument_id" },
                    { "data": "instrument_display_name" },
                    { "data": "instrument_type_id" },
                    { "data": "instrument_type_sub_category_id" }
                ],
                "order": [[1, 'asc']]
            } );

            // Add event listener for opening and closing details
            $('#instrument-table tbody').on('click', 'td.details-control', function () {
                let tr = $(this).closest('tr');
                let row = table.row( tr );

                if ( row.child.isShown() ) {
                    // This row is already open - close it
                    row.child.hide();
                    tr.removeClass('shown');
                } else {
                    // Open this row
                    row.child( format(row.data()) ).show();
                    tr.addClass('shown');
                }
            } );
        });
    </script>
</head>
<body>
    <div id="instrument-table-container">
        <table id="instrument-table" class="table table-striped table-bordered" style="width:100%">
            <thead>
            <tr>
                <th></th>
                <th>Thumbnail</th>
                <th>Name</th>
                <th>Code</th>
                <th>Office</th>
                <th>Salary</th>
            </tr>
            </thead>
            <tfoot>
            <tr>
                <th></th>
                <th>Thumbnail</th>
                <th>Name</th>
                <th>Code</th>
                <th>Office</th>
                <th>Salary</th>
            </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>


