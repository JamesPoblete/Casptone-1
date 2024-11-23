// maindashboard.js

document.addEventListener('DOMContentLoaded', function () {
    const filterToggle = document.getElementById('filterToggle');
    const filterPanel = document.getElementById('filterPanel');
    const applyButton = document.querySelector('.apply-btn');
    const resetButton = document.querySelector('.reset-btn');
    const filterForm = document.querySelector('.filter-form');
    const loadingSpinner = document.getElementById('loadingSpinner'); // Ensure spinner exists or remove related code

    // Toggle filter panel visibility when filter button is clicked
    filterToggle.addEventListener('click', function (e) {
        e.stopPropagation(); // Prevent event from bubbling up to document
        filterPanel.classList.toggle('active');
    });

    // Close filter panel when clicking outside of it
    document.addEventListener('click', function (e) {
        if (
            filterPanel.classList.contains('active') &&
            !filterPanel.contains(e.target) &&
            e.target !== filterToggle
        ) {
            filterPanel.classList.remove('active');
        }
    });

    // Handle Apply button click to show a loading spinner
    if (applyButton && loadingSpinner) {
        applyButton.addEventListener('click', function () {
            // Show loading spinner
            loadingSpinner.style.display = 'block';

            // The form will submit naturally, causing a page reload
            // Hide spinner after a short delay to allow form submission
            setTimeout(function () {
                loadingSpinner.style.display = 'none';
            }, 1000); // Adjust the delay as needed
        });
    }

    // Prevent panel from closing when clicking inside the panel
    filterPanel.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    // Flip Animation and Calendar Initialization
    // Ensure the necessary data is available
    if (typeof calendarEvents !== 'undefined' && typeof weekdayCounts !== 'undefined' && typeof maxCustomers !== 'undefined') {
        // Initialize the calendar
        const calendarEl = document.getElementById('customerCalendar');
        const today = new Date();

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            timeZone: 'Asia/Manila',
            headerToolbar: { // Enable headerToolbar for navigation and title
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            contentHeight: 'auto', // Adjust height to fit content
            selectable: false,
            editable: false,
            scrollTime: '00:00:00', // Remove scrollable area
            dayMaxEvents: false, // Show all events
            events: calendarEvents.map(event => ({
                title: `${event.count} Customers`,
                start: event.date,
                allDay: true,
                backgroundColor: getColor(event.count),
                borderColor: getColor(event.count)
            })),
            eventDidMount: function(info) {
                tippy(info.el, {
                    content: `Date: ${info.event.start.toDateString()}<br>Customers: ${info.event.title}`,
                    allowHTML: true,
                    theme: 'light',
                });
            },
            // Highlight today's date
            dayCellDidMount: function(info) {
                const cellDate = info.date;
                if (
                    cellDate.getDate() === today.getDate() &&
                    cellDate.getMonth() === today.getMonth() &&
                    cellDate.getFullYear() === today.getFullYear()
                ) {
                    info.el.style.border = '2px solid #1A1A40';
                }
            }
        });

        calendar.render();

        // Function to determine event color based on customer count
        function getColor(count) {
            // Define color thresholds
            if (count >= (maxCustomers * 0.75)) {
                return '#FF5733'; // High customer count - Red
            } else if (count >= (maxCustomers * 0.5)) {
                return '#FFC300'; // Medium customer count - Yellow
            } else {
                return '#28B463'; // Low customer count - Green
            }
        }

        // Initialize Chart.js for customer counts per weekday in the selected month
        const ctx = document.getElementById('customerChart').getContext('2d');

        const chartLabels = Object.keys(weekdayCounts);
        const chartData = Object.values(weekdayCounts);

        const customerChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Customers',
                    data: chartData,
                    backgroundColor: '#1A1A40',
                    borderColor: '#1A1A40',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ` ${context.parsed.y} Customers`;
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision:0
                        }
                    }
                }
            }
        });

        // Handle flip animation on pagination button clicks
        const flipCard = document.getElementById('flipCard');

        // Function to flip to chart
        function flipToChart() {
            if (!flipCard.classList.contains('flipped')) {
                flipCard.classList.add('flipped');
            }
        }

        // Function to flip back to calendar
        function flipBackToCalendar() {
            if (flipCard.classList.contains('flipped')) {
                flipCard.classList.remove('flipped');
            }
        }

        // Function to attach event listeners to calendar buttons
        function attachCalendarButtons() {
            const prevButton = document.querySelector('.fc-prev-button');
            const nextButton = document.querySelector('.fc-next-button');
            const todayButton = document.querySelector('.fc-today-button');

            if (prevButton && !prevButton.hasAttribute('data-flip-listener')) {
                prevButton.addEventListener('click', flipToChart);
                prevButton.setAttribute('data-flip-listener', 'true');
            }

            if (nextButton && !nextButton.hasAttribute('data-flip-listener')) {
                nextButton.addEventListener('click', flipToChart);
                nextButton.setAttribute('data-flip-listener', 'true');
            }

            if (todayButton && !todayButton.hasAttribute('data-flip-listener')) {
                todayButton.addEventListener('click', flipToChart);
                todayButton.setAttribute('data-flip-listener', 'true');
            }
        }

        // Attach listeners on initial load
        attachCalendarButtons();

        // Attach listeners on view changes
        calendar.on('datesSet', function() {
            attachCalendarButtons();
        });

        // Attach event listener to the chart canvas to flip back to calendar
        const customerChartCanvas = document.getElementById('customerChart');
        customerChartCanvas.addEventListener('click', flipBackToCalendar);
    }
});
