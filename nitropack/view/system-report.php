<?php
$settings = new \NitroPack\WordPress\Settings(); ?>

<div class="grid grid-cols-1 gap-6">
	<div class="col-span-1">
		<div class="card">
			<?php $settings->system_report->render(); ?>
		</div>
		<div class="card">
			<div class="card-body">
				<div class="nitro-option" id="minimum-log-level-widget">
					<div class="nitro-option-main">
						<?php $settings->logger->render(); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>