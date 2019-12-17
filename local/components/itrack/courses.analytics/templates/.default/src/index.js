import './style.scss';
import 'bootstrap';
import 'bootstrap-datepicker';
import 'bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css';
import 'bootstrap-datepicker/js/locales/bootstrap-datepicker.ru';

const App = function () {
    return {
        endpoint: '/bitrix/services/main/ajax.php?c=itrack:courses.analytics&mode=class',
        //coursesUrl: 'courses.json',
        //courseListUrl: 'course-list.json',
        paymentsUrl: 'payments.json',
        courses: [],
        payments: [],
        filter: {
            date: null,
            course: null,
            categoryId: $('#filter').data('category')
        },

        initializators: {
            initCourses: function (self) {
                let container = $('#courses-table');
                let postData = new FormData();

                if (!container.length) {
                    return;
                }

                postData.append('date', self.filter.date);
                postData.append('course', self.filter.course);
                postData.append('categoryId', self.filter.categoryId);
                postData.append('sessid', BX.message('bitrix_sessid'));

                fetch(self.endpoint + '&action=getCourses', {
                    method: 'POST',
                    body: postData
                }).then(res => res.json()).then(res => {
                    self.courses = res.data;
                    self.renderCourses();
                })
            },
            initCourseList: function (self) {
                fetch(self.endpoint + '&action=getCoursesList&sessid=' + BX.message('bitrix_sessid'))
                    .then(res => res.json())
                    .then(res => {
                        res.data.forEach(course => {
                            $('#filter-course').append(`<option value="${course.id}">${course.name}</option>`);
                        });
                    })
            },
            initDatepicker: function () {
                $('.datepicker').datepicker({
                    format: 'yyyy-mm-dd',
                    language: 'ru'
                });
            },
            initForm: function (self) {
                $('#filter-date').change(function () {
                    $(this).closest('form').submit();
                });

                $('#filter-course').change(function () {
                    $(this).closest('form').submit();
                });

                $('#filter').submit(function () {
                    self.filter.date = $('#filter-date').val();
                    self.filter.course = $('#filter-course').val();

                    self.initializators.initCourses(self);

                    return false;
                });
            },
            initHandlers: function () {
                $('body').on('show.bs.collapse', '.collapse', function () {
                    $('[data-target=".c' + $(this).data('rand') + '"]').text('-');
                });

                $('body').on('hide.bs.collapse', '.collapse', function () {
                    $('[data-target=".c' + $(this).data('rand') + '"]').text('+');
                });
            },
            initPayments: function (self) {
                let container = $('#payments-table');
                let postData = new FormData();

                if (!container.length) {
                    return;
                }

                postData.append('date', self.filter.date);
                postData.append('course', self.filter.course);
                postData.append('categoryId', self.filter.categoryId);
                postData.append('sessid', BX.message('bitrix_sessid'));

                fetch(self.endpoint + '&action=getPayments', {
                    method: 'POST',
                    body: postData
                })
                    .then(res => res.json())
                    .then(res => {
                        self.payments = res.data;
                        self.renderPayments();
                    })
            },
            initSidepanel: function(self) {
                BX.SidePanel.Instance.bindAnchors({
                    rules:
                        [
                            {
                                condition: [
                                    new RegExp(".*/tasks/task/view/[0-9]+/")
                                ],
                                loader: "tasks:view-loader"
                            },
                        ]
                });
            }
        },

        renderCourses: function () {
            let container = $('#courses-table');

            let html = `
<table class="table table-bordered table-hover">
    <thead>
    <tr>
        <th>Сессия \\ Ученик</th>
        ${this.templates.students(this.courses)}
    </tr>
    </thead>
    <tbody>
    ${this._renderCoursesBody()}
    </tbody>
</table>`;

            container.html(html);
        },

        renderPayments: function () {
            let container = $('#payments-table');

            let html = `
<table class="table table-bordered table-hover">
    <thead>
    <tr>
        <th>Сессия \\ Ученик</th>
        ${this.templates.students(this.payments)}
    </tr>
    </thead>
    <tbody>
    ${this._renderPaymentsBody()}
    </tbody>
</table>`;

            container.html(html);
        },

        templates: {
            collapseButton: function (id) {
                return `<button class="btn btn-default btn-sm collapse-course" data-toggle="collapse" data-target=".c${id}" role="button" aria-expanded="true">+</button> `;
            },
            payment: function (payment) {
                let now = new Date();

                let paymentDate = new Date(payment.payTill);

                let divClass = 'default';

                if (payment.paid) {
                    divClass = 'success';
                } else if (paymentDate < now) {
                    divClass = 'danger';
                }

                return `<div class="payment-alert alert alert-${divClass}">${payment.sum.toLocaleString()}<br>${paymentDate.toLocaleDateString()}</div>`;
            },
            paymentsData: function (studentList, data) {
                let html = '';

                studentList.forEach(student => {
                    html += `<td>${data[student] !== undefined ? this.payment(data[student]) : ''}</td>`;
                });

                return html;
            },
            students: function (data) {
                let html = '';

                data.forEach(student => {
                    html += `<th><a href="${student.href}">${student.name}</a></th>`;
                });

                return html;
            },
            studentsData: function (studentList, studentData) {
                let html = '';

                studentList.forEach(student => {
                    html += `<td>${studentData[student] !== undefined ? this.tasks(studentData[student].tasks) : ''}</td>`;
                });

                return html;
            },
            tasks: function (tasks) {
                let html = '<ul class="list-group">';
                let now = new Date();

                tasks.forEach(task => {
                    let taskDate = new Date(task.completeTill);

                    let listClass = 'default';

                    if (task.completed) {
                        listClass = 'success';
                    } else if (taskDate < now) {
                        listClass = 'danger';
                    }

                    html += `<li class="list-group-item list-group-item-${listClass}"><a class="list-group-item-${listClass}" href="${task.url}">${taskDate.toLocaleDateString()}</a></li>`;
                });

                html += '</ul>';

                return html;
            }
        },

        _renderCoursesBody: function () {
            if (!this.courses) {
                return '';
            }

            let data = {};
            let studentList = [];

            this.courses.forEach(student => {
                studentList.push(student.name);

                student.sessions.forEach(session => {
                    if (data[session.name] === undefined) {
                        data[session.name] = {
                            name: session.name,
                            sections: {},
                            students: {}
                        };
                    }

                    data[session.name].students[student.name] = {
                        name: student.name,
                        tasks: []
                    };

                    session.sections.forEach(section => {
                        if (data[session.name].sections[section.name] === undefined) {
                            data[session.name].sections[section.name] = {
                                name: section.name,
                                students: {}
                            };
                        }

                        data[session.name].sections[section.name].students[student.name] = {
                            tasks: section.tasks
                        };

                        data[session.name].students[student.name].tasks = data[session.name].students[student.name].tasks.concat(section.tasks);
                    });
                });
            });

            console.log(Object.entries(data));

            let html = '';

            let tasksAll = 0;
            let tasksCompletedHtml = '';
            let tasksCompletedPercentHtml = '';

            Object.entries(data).forEach(session => {
                let rand = window.crypto.getRandomValues(new Int8Array(1));
                let button = '';
                if(Object.entries(session[1].sections).length) {
                    button = this.templates.collapseButton(rand[0]);
                    tasksAll += Object.entries(session[1].sections).length;
                }

                html += `<tr><th>${button + session[0]}</th>${this.templates.studentsData(studentList, session[1].students)}</tr>`;

                Object.entries(session[1].sections).forEach(section => {
                    html += `<tr data-rand="${rand[0]}" class="c${rand[0]} collapse"><th>${section[0]}</th>${this.templates.studentsData(studentList, section[1].students)}</tr>`;
                });
            });

            this.courses.forEach(student => {
                tasksCompletedHtml += `<td>${student.countCompleted}</td>`;
                tasksCompletedPercentHtml += `<td>${tasksAll > 0 ? Math.round((student.countCompleted/tasksAll)*100) : ''}</td>`
            });
            html += `<tr><th>Кол-во выполненных задач</th>${tasksCompletedHtml}</tr>`;
            html += `<tr><th>% выполнения</th>${tasksCompletedPercentHtml}</tr>`;

            return html;
        },

        _renderPaymentsBody: function () {
            if (!this.payments) {
                return '';
            }

            let data = {};
            let studentList = [];

            this.payments.forEach(student => {
                studentList.push(student.name);

                student.payments.forEach(payment => {
                    if (data[payment.name] === undefined) {
                        data[payment.name] = {
                            name: payment.name,
                            students: {}
                        };
                    }

                    data[payment.name].students[student.name] = payment;
                });
            });

            console.log(Object.entries(data));

            let html = '';

            Object.entries(data).forEach(item => {
                html += `<tr><th>${item[0]}</th>${this.templates.paymentsData(studentList, item[1].students)}</tr>`;
            });

            let fullPrice = 0;
            let paidPrice = 0;
            let fullPriceHtml = '';
            let paidPriceHtml = '';
            let unpaidPriceHtml = '';
            this.payments.forEach(student => {
                fullPrice += student.fullPrice;
                paidPrice += student.currentPaid;
                fullPriceHtml += `<td>${student.fullPrice}</td>`;
                paidPriceHtml += `<td>${student.currentPaid}</td>`;
                unpaidPriceHtml += `<td>${student.fullPrice - student.currentPaid}</td>`;
            });

            html += `<tr><th>Полученная сумма<span class="full-sum">${paidPrice}</span></th>${paidPriceHtml}`;
            html += `<tr><th>Ожидаемая сумма<span class="full-sum">${fullPrice - paidPrice}</span></th>${unpaidPriceHtml}`;
            html += `<tr><th>Итого<span class="full-sum">${fullPrice}</span></th>${fullPriceHtml}`;

            return html;
        },
    };
};

$(function () {
    let app = new App();

    for (let fn in app.initializators) {
        app.initializators[fn](app);
    }
});
