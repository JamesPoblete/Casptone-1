// Bar Chart for Monthly Sales
const ctxBar = document.getElementById('barChart').getContext('2d');
const barChart = new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Monthly Sales (₱)',
            data: salesByMonth, // Use the PHP data passed above
            backgroundColor: [
                'rgba(54, 162, 235, 0.6)',
                'rgba(255, 206, 86, 0.6)',
                'rgba(75, 192, 192, 0.6)',
                'rgba(153, 102, 255, 0.6)',
                'rgba(255, 159, 64, 0.6)',
                'rgba(255, 99, 132, 0.6)',
                'rgba(75, 192, 192, 0.6)',
                'rgba(153, 102, 255, 0.6)',
                'rgba(255, 159, 64, 0.6)',
                'rgba(54, 162, 235, 0.6)',
                'rgba(255, 206, 86, 0.6)',
                'rgba(75, 192, 192, 0.6)'
            ],
            borderColor: [
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱ ' + value;
                    }
                }
            }
        },
        responsive: true,
        maintainAspectRatio: false
    }
});



// Pie Chart
const ctxPie = document.getElementById('pieChart').getContext('2d');
const pieChart = new Chart(ctxPie, {
  type: 'pie',
  data: {
    labels: ['Product 1', 'Product 2', 'Product 3'],
    datasets: [{
      data: [50, 30, 20],
      backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false, // This allows the chart to fill its container
  }
});

// Line Chart for Monthly Sales with Prediction
const ctxPrediction = document.getElementById('predictionChart').getContext('2d');

// Clone the salesByMonth array and add the prediction
const salesWithPrediction = [...salesByMonth];
salesWithPrediction.push(nextMonthPrediction);

// Extend labels to include next month
const extendedLabels = [...salesLabels];
const nextMonthDate = new Date();
nextMonthDate.setMonth(nextMonthDate.getMonth() + 1);
const nextMonthName = nextMonthDate.toLocaleString('default', { month: 'short' });
extendedLabels.push(nextMonthName);

// Create the chart
const predictionChart = new Chart(ctxPrediction, {
    type: 'line',
    data: {
        labels: extendedLabels,
        datasets: [{
            label: 'Monthly Sales (₱)',
            data: salesWithPrediction,
            borderColor: 'rgba(75, 192, 192, 1)', // Teal line
            backgroundColor: 'rgba(75, 192, 192, 0.2)', // Teal fill
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgba(75, 192, 192, 1)',
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 14, weight: 'bold' },
                    color: '#666'
                }
            },
            y: {
                grid: {
                    color: 'rgba(200, 200, 200, 0.5)',
                    borderDash: [5, 5]
                },
                ticks: {
                    callback: function(value) {
                        return '₱ ' + value;
                    },
                    font: { size: 12 },
                    color: '#666'
                },
                beginAtZero: true
            }
        },
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(75, 192, 192, 0.8)',
                titleFont: { size: 16, weight: 'bold' },
                bodyFont: { size: 14 },
                callbacks: {
                    label: function(context) {
                        return '₱ ' + context.raw.toLocaleString();
                    }
                }
            }
        }
    }
});