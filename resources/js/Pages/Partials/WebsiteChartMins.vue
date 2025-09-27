<script setup>
import WebsiteChart from './WebsiteChart.vue'
import { sharedChartOptions, sharedChartDataset1 } from '@/Utils/consts'

const props = defineProps({
	data: Object
})

const data = {
	datasets: [{
		...sharedChartDataset1,
		label: 'Response',
		data: props.data
	}]
}

const options = JSON.parse(JSON.stringify(sharedChartOptions))

options.scales.x.time = {
	unit: 'hour',
	tooltipFormat: 'HH:mm',
	displayFormats: {
		hour: 'HH:mm'
	}
}

options.scales.y = {
	type: 'linear',
	position: 'left',
	min: 0,
	grid: {
		color: options.chartSyling.gridColor,
	},
	ticks: {
		color: options.chartSyling.color,
		callback: val => parseFloat(Math.round(val/10) / 100) + 's'
	}
}

options.plugins.legend.display = false
options.plugins.tooltip.callbacks.label = ctx => `${ctx.dataset.label}: ${ctx.parsed.y}ms`
</script>

<template>
	<WebsiteChart :data :options />
</template>