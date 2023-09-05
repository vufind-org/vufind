/*global VuFind, Chart */
VuFind.register('explain', function explain() {

  function _getBarChartColor(score, maxScore) {
    let percentage = 0;
    if (maxScore > 0) {
      percentage = score / maxScore;
    }
    if (percentage < 0.2) {
      return {
        'border': '#7D0C0C',
        'fill': 'indianred'
      };
    }
    if (percentage < 0.6) {
      return {
        'border': '#A0963C',
        'fill': 'khaki'
      };
    }
    return {
      'border': '#003B07',
      'fill': 'seagreen'
    };
  }

  function _setupResultListChart() {
    document.querySelectorAll('.result-list-explain .bar-chart').forEach(function createResultListChart(barChart) {
      const maxScore = barChart.dataset.maxScore;
      const score = barChart.dataset.score;
      const diff = Math.abs(maxScore - score);
      const color = _getBarChartColor(score, maxScore);
      const title = barChart.dataset.chartTitle;
      new Chart(barChart, {
        type: 'bar',
        data: {
          labels: ['score'],
          datasets: [
            {
              label: title,
              data: [score],
              backgroundColor: color.fill,
              borderColor: color.border,
              borderWidth: 2,
              borderSkipped: false,
            },
            {
              label: 'difference to top score',
              data: [diff],
              backgroundColor: '#ddd',
              borderColor: '#aaa',
              borderWidth: 2,
              borderSkipped: false,
            },
          ]
        },
        options: {
          events: [],
          aspectRatio: 1 | 4,
          plugins: {
            legend: {
              display: false,
            },
          },
          indexAxis: 'y',
          scales: {
            x: {
              display: false,
              stacked: true,
              max: maxScore,
            },
            y: {
              border: {
                display: false,
              },
              grid: {
                display: false,
              },
              ticks: {
                display: false,
                min: 0
              },
              stacked: true,
            },
          }
        }
      });
    });
  }


  function _setupExplainPieChart() {
    const pieChart = document.getElementById('explain-pie-chart');
    if (pieChart != null) {
      const data = pieChart.dataset.chartData.split(', ');
      const labels = pieChart.dataset.chartLabels.split(', ');
      new Chart(pieChart, {
        type: 'pie',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'score',
              data: data,
              borderColor: "black",
              borderWidth: 0.2,
              backgroundColor: [
                "#ffa600",
                "#ff7c43",
                "#f95d6a",
                "#d45087",
                "#a05195",
                "#665191",
                "#2f4b7c",
                "#003f5c",
              ],
            }
          ]
        },
        options: {
          responsive: true,
          aspectRatio: 1 | 1,
          maintainAspectRatio: false,
          devicePixelRatio: 1.8, //fixes "blurry" text
          plugins: {
            legend: {
              position: 'right',
            },
          }
        }
      });
    }
  }

  function _setupExplainColumnChart() {
    const columnChart = document.getElementById('explain-column-chart');
    const maxScore = columnChart.dataset.maxScore;
    const score = columnChart.dataset.score;
    const diff = Math.abs(maxScore - score);
    const color = _getBarChartColor(score, maxScore);
    const title = columnChart.dataset.chartTitle;
    new Chart(columnChart, {
      type: 'bar',
      data: {
        labels: ['relevance score'],
        datasets: [
          {
            label: 'title score',
            data: [score],
            backgroundColor: color.fill,
            borderColor: color.border,
            borderWidth: 2,
            borderSkipped: false,
          },
          {
            label: 'difference to top score',
            data: [diff],
            backgroundColor: '#ddd',
            borderColor: '#aaa',
            borderWidth: 2,
            borderSkipped: false,
          },
        ]
      },
      options: {
        devicePixelRatio: 1.8, //fixes "blurry" text
        responsive: true,
        aspectRatio: 1 | 4,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          title: {
            display: true,
            text: title,
          }
        },
        indexAxis: 'y',
        scales: {
          x: {
            stacked: true,
            max: maxScore,
          },
          y: {
            ticks: {
              display: false,
              min: 0,
              beginAtZero: true,
            },
            stacked: true,
          },
        }
      }
    });
  }

  function init() {
    _setupResultListChart();
    _setupExplainPieChart();
    _setupExplainColumnChart();
  }

  return {
    init: init
  };
});

