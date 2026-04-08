

<?php
//1.Cập nhật lần cuối: ngày 30/10/2025 - 10:45 Sáng

$current_time = time();
$current_date = date('d-m-Y', $current_time);
$current_time = date('H:i:s', $current_time);

?>
<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>
<script src="https://cdn.tailwindcss.com"></script>

<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<!-- Highcharts -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>

<link rel="stylesheet" href="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/style.css">

<?php
$module_path = 'admin.php?page=dashboard-layout';

?>
<style>
    .btn-action {
        background-color: #3498db;
        color: white;
        padding: 12px 20px;
        border-radius: 5px;
        font-size: 16px;
        transition: background-color 0.3s ease;
        cursor: pointer;
    }


</style>
<body class="bg-gray-100 font-sans">

<div class="container mx-auto p-6">
	<div class="bg-white shadow-lg rounded-lg p-6">

		<h1 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Dasboard Layout</h1>

		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-6">

			<div class="bg-gray-50 p-4 rounded-lg shadow flex flex-col justify-between items-center">
				<h2 class="text-xl font-semibold text-gray-800 mb-4">Dashboard </h2>
				<div class="flex gap-2">
					<a href="<?php echo $module_path; ?>&sub=dashboard" class="btn-action">Xem Danh Sách</a>
				</div>
			</div>

			<div class="bg-gray-50 p-4 rounded-lg shadow flex flex-col justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800 mb-4"> Quản lí User</h2>
				<div class="flex gap-2">
					<a href="<?php echo $module_path; ?>&sub=user-management" class="btn-action">Xem Danh Sách</a>
				</div>
			</div>

			<div class="bg-gray-50 p-4 rounded-lg shadow flex flex-col justify-between items-center">
				<h2 class="text-xl font-semibold text-gray-800 mb-4"> Lịch sử lượt chơi </h2>
				<div class="flex gap-2">
					<a href="<?php echo $module_path; ?>&sub=play-history" class="btn-action">Xem Danh Sách</a>
				</div>
			</div>

			<div class="bg-gray-50 p-4 rounded-lg shadow flex flex-col justify-between items-center">
				<h2 class="text-xl font-semibold text-gray-800 mb-4">Nhật ký hệ thống</h2>
				<div class="flex gap-2">
					<a href="<?php echo $module_path; ?>&sub=system-log" class="btn-action">Xem Danh Sách</a>
				</div>
			</div>


		</div>

	</div>
</div>

</body>