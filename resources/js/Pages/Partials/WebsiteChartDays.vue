<script setup>
import WebsiteChart from './WebsiteChart.vue'
import { sharedChartOptions, sharedChartDataset1, sharedChartDataset2 } from '@/Utils/consts'

const props = defineProps({
	data: Object
})

const options = structuredClone(sharedChartOptions)

const data = {
	datasets: [{
		...sharedChartDataset1,
		label: 'Availability',
		data: props.data.success_rate,
		yAxisID: 'y1',
		order: 2
	}, {
		...sharedChartDataset2,
		label: 'Avg Response',
		data: props.data.avg_duration,
		yAxisID: 'y2',
		order: 1
	}]
}

// Compute Y axis ranges
const minSuccess = Math.min(98, Math.floor(Math.max(0, Math.min(...data.datasets[0].data.map(s => parseFloat(s.y))) - 1)))
const minDuration = Math.floor((Math.min(...data.datasets[1].data.map(s => parseFloat(s.y))) - 100) / 100) * 100
const maxDuration = Math.ceil((Math.max(...data.datasets[1].data.map(s => parseFloat(s.y))) + 100) / 100) * 100

options.scales.y1 = {
	type: 'linear',
	position: 'left',
	grid: {
		color: options.chartSyling.gridColor,
	},
	min: minSuccess,
	max: 100,
	ticks: {
		color: options.chartSyling.color,
		precision: 0,
		callback: val => `${val}%`
	}
}
options.scales.y2 = {
	type: 'linear',
	position: 'right',
	grid: {
		color: options.chartSyling.gridColor,
		drawOnChartArea: false
	},
	suggestedMin: minDuration,
	suggestedMax: maxDuration,
	ticks: {
		maxTicksLimit: 8,
		color: options.chartSyling.color,
		includeBounds: true,
		callback: val => parseFloat(Math.round(val/10) / 100) + 's'
	}
}
options.scales.x.time.tooltipFormat = "dd.M.y"
options.plugins.tooltip.callbacks.label = ctx => ctx.dataset.label + ': ' + (ctx.dataset.yAxisID === 'y1' ? `${ctx.parsed.y}%` : `${ctx.parsed.y}ms`)
</script>

<template>
	<WebsiteChart :data :options />
</template>