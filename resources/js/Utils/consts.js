export const serverOptions = [
	{
		icon: 'php',
		title: 'PHP',
		value: 'php'
	}, {
		icon: 'node',
		title: 'NodeJS',
		value: 'node'
	}
]
export const timeoutOptions = [5, 8, 10, 12, 15].map(i => ({title: `${i}s`, value: i}))
export const rpaOptions = Array.from({length: 4}).map((i, index) => ({title: `${index+2}x`, value: index+2}))
export const updateOptions = [
	{
		title: 'Inactive',
		value: false,
		color: 'danger'
	}, {
		title: 'Active',
		value: true,
		color: 'success'
	}
]

export const monthsMap = {
	1: 'January',
	2: 'February',
	3: 'March',
	4: 'April',
	5: 'May',
	6: 'June',
	7: 'July',
	8: 'August',
	9: 'September',
	10: 'October',
	11: 'November',
	12: 'December',
}
export const daysMap = {
	1: 'Mon',
	2: 'Tue',
	3: 'Wed',
	4: 'Thu',
	5: 'Fri',
	6: 'Sat',
	7: 'Sun'
}

const chartSyling = {
	color: '#888',
	gridColor: '#8884'
}
export const sharedChartOptions = {
	chartSyling: chartSyling,
	responsive: true,
	maintainAspectRatio: false,
	clip: false,
	interaction: {
		mode: 'index',
		intersect: false
	},
	animation: {
		duration: 300
	},
	elements: {
		line: {
			tension: 0,
			borderWidth: 2,
			fill: true
		},
		point: {
			radius: 0,
			hoverRadius: 6.5,
			borderWidth: 0,
			hoverBorderWidth: 2
		}
	},
	scales: {
		x: {
			type: 'time',
			time: {
				unit: 'day',
				displayFormats: {
					day: 'dd.M'
				}
			},
			ticks: {
				color: chartSyling.color,
				maxRotation: 0,
				autoSkip: true,
				autoSkipPadding: 8
			},
			grid: {
				color: chartSyling.gridColor,
				drawOnChartArea: false
			}
		}
	},
	plugins: {
		legend: {
			reverse: true,
			display: true,
			labels: {
				padding: 8,
				color: chartSyling.color,
				boxHeight: 9.5,
				usePointStyle: true
			}
		},
		tooltip: {
			reverse: true,
			usePointStyle: true,
			boxWidth: 11,
			boxHeight: 11,
			boxPadding: 4,
			callbacks: {}
		}
	}
}

function getSharedDataset(color) {
	return {
		borderColor: color,
		pointBackgroundColor: color,
		pointBorderColor: `color-mix(in srgb, ${color} 15%, #fff)`,
		backgroundColor: (ctx) => {
			const canvas = ctx.chart.ctx;
			const gradient = canvas.createLinearGradient(0, 0, 0, 350);

			gradient.addColorStop(0, `${color}66`);
			gradient.addColorStop(1, `${color}00`);

			return gradient;
		},
	}
}

export const sharedChartDataset1 = getSharedDataset('#008FFB')
// export const sharedChartDataset2 = getSharedDataset('#03E396')
export const sharedChartDataset2 = getSharedDataset('#04d38b')