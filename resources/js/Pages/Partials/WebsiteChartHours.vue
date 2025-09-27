<script setup>
import WebsiteChart from './WebsiteChart.vue'
import { sharedChartOptions, sharedChartDataset1, sharedChartDataset2 } from '@/Utils/consts'

const props = defineProps({
	data: Object
})

const data = {
	datasets: [{
		...sharedChartDataset2,
		label: 'Min response time',
		data: props.data.min
	}, {
		...sharedChartDataset1,
		label: 'Max response time',
		data: props.data.max
	}]
}

const options = JSON.parse(JSON.stringify(sharedChartOptions))

options.scales.y = {
	type: 'linear',
	position: 'left',
	min: 0,
	// stacked: true,
	grid: {
		color: options.chartSyling.gridColor,
	},
	ticks: {
		color: options.chartSyling.color,
		callback: val => parseFloat(Math.round(val/10) / 100) + 's'
	}
}

options.scales.x.type = 'linear'
options.scales.x.ticks.precision = 0
options.scales.x.ticks.maxTicksLimit = 2
options.scales.x.max = props.data.min.at(props.data.min.length-1).x
options.scales.x.min = props.data.min[0].x
options.scales.x.ticks.callback = (val, index) => index == 0 ? `${val}h` : `${val+1}h`

options.plugins.tooltip.callbacks.title = ctx => `${ctx[0].raw.x}:00 - ${ctx[0].raw.x+1}:00`
options.plugins.tooltip.callbacks.label = ctx => `${ctx.dataset.label}: ${ctx.parsed.y}ms`
</script>

<template>
	<WebsiteChart :data :options />
</template>