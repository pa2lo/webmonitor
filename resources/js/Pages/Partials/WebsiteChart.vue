<script setup>
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, Tooltip, Legend, LineElement, PointElement, LinearScale, TimeScale, Filler } from 'chart.js'
import 'chartjs-adapter-date-fns'

ChartJS.register( Tooltip, Legend, LineElement, PointElement, LinearScale, TimeScale, Filler )

const props = defineProps({
	data: Object,
	options: Object
})

props.options.plugins.tooltip.callbacks.labelColor = function (context) {
	return {
		borderColor: context.dataset.pointBackgroundColor,
		backgroundColor: context.dataset.pointBackgroundColor,
		borderWidth: 0
	}
}

const legendMargin = {
	id: 'paddingBelowLegends',
	beforeInit: function(chart, options) {
		const originalFit = chart.legend.fit
		chart.legend.fit = function fit() {
			if (originalFit) originalFit.call(this)
			return this.height += 4
		}
	}
}

const mouseline = {
	id: 'mouseLine',
	beforeDatasetsDraw(chart, args, plugins) {
		const { ctx, chartArea: { top, bottom, height } } = chart

		ctx.save()
		ctx.setLineDash([5, 4])

		chart.getDatasetMeta(0).data.forEach((dataPoint, index) => {
			if (dataPoint.active == true) {
				ctx.beginPath()
				ctx.strokeStyle = "#8886"
				ctx.moveTo(dataPoint.x, top)
				ctx.lineTo(dataPoint.x, bottom)
				ctx.stroke()
			}
		})

		ctx.setLineDash([])
		ctx.restore()
	}
}
</script>

<template>
	<div class="graph-wrapper-cjs line">
		<Line :data :plugins="[mouseline, legendMargin]" :options />
	</div>
</template>