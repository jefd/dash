//const BASE_URL =  "https://epic.noaa.gov";
const BASE_URL =  "https://rayv-webix4.jpl.nasa.gov/devel/ep";
//const BASE_URL =  "";
const API_PATH = "/wp-json/dash/v1";

const INITIAL_OWNER = "ufs-community";
const INITIAL_REPO = "ufs-weather-model";
const INITIAL_METRIC = "views";

const CHART_OPTS = {
    'responsive':true,
    plugins: {
        legend: {
            display: true,
            position: 'top',
            align: 'center',
            labels: {
                fontSize: 16,
            }
        },

    },
    scales: {
        y: {
            'drawOnChartArea': false,
            'lineWidth': 2
        },
        x: {
            'drawOnChartArea': false,
            'lineWidth': 2
        }
    }

};

/*
const REPOS = [
    {owner: 'ufs-community', name: 'ufs-weather-model', title: 'Weather Model', minDate: '2022-08-27'}, 
    {owner: 'ufs-community', name: 'ufs-srweather-app', title: 'Short Range Weather App', minDate: '2022-08-26'},
];
*/

const METRICS = [
    {name: 'views', title: 'Views'}, 
    {name: 'clones', title: 'Clones'}, 
    {name: 'frequency', title: 'Additions and Deletions'}, 
    {name: 'commits', title: 'Commits'}, 
    {name: 'contributors', title: 'Top Contributors'}, 
    {name: 'releases', title: 'Releases'}, 
];


/********************** for testing only ***************************/
const RELEASE_DATA = {
    releases: [
        {'name': 'ufs-srw-v2.0.0', 'date': '2022-06-22T20:27:34Z'}, 
        {'name': 'ufs-v1.0.1', 'date': '2021-09-15T22:17:57Z'}, 
        {'name': 'ufs-v1.0.0', 'date': '2021-03-03T20:46:26Z'}
    ]
};

const CONTRIBUTOR_DATA = {
    count: 30, 
    'top': [
        {login: 'SamuelTrahanNOAA', contributions: 163}, 
        {login: 'junwang-noaa', contributions: 158}, 
        {login: 'climbfuji', contributions: 74}
    ]
}
/*******************************************************************/


function Dash(initialVnode) {

    let model = {
        selectedOwner: INITIAL_OWNER,
        selectedRepo: INITIAL_REPO,
        selectedMetric: INITIAL_METRIC,
        owner: INITIAL_OWNER,
        repo: INITIAL_REPO,
        metric: INITIAL_METRIC,
        repos: null,
        minDate: null,
        startDate: getDefaultStartDate(),
        endDate: getMaxDate(),
        data: null,
        chart: null,
	    loaded: false,	
        error: "",
        showDatePicker: true,
    };

    function getUrl() {
        return `${BASE_URL}${API_PATH}/${model.owner}/${model.repo}/${model.metric}`;
    }

    function getName(lst, name) {
        let m = {};

        lst.forEach(function(obj) {
            m[obj.name] = obj.title
        });

        return m[name];
    }

    function getMinDate(owner, repo) {
        let repos = model.repos;
        for (let idx in repos) {
            let rep = repos[idx];
            if (rep['owner'] === owner && rep['name'] === repo)
                return rep['minDate'];
        }
    }

    function getMaxDate() {
        let d = new Date();
        //return d.toISOString().substring(0, 10);
        //y = d.getDate() - 1;
        //d.setDate(y);
        return d.toISOString().substring(0, 10);
    }

    function getDefaultStartDate() {
        let d = new Date();
        let y = d.getDate() - 14;
        d.setDate(y);
        return d.toISOString().substring(0, 10);
    }

    function addDays(datestring, days) {
        let d = new Date(datestring);
        let y = d.getDate() + days;
        d.setDate(y);
        return d.toISOString().substring(0, 10);
    }


    /******************** Update Functions *********************/
    function repoCallback(e) {
        //e.redraw = false;
        model.selectedOwner = e.target.value.split('/')[0];
        model.selectedRepo = e.target.value.split('/')[1];
        model.minDate = getMinDate(model.selectedOwner, model.selectedRepo);

        if (model.startDate < model.minDate) {
            model.startDate = model.minDate;
        }
    }
    
    function metricCallback(e) {
        //e.redraw = false;
        model.selectedMetric = e.target.value;
    }

    function submitCallback(e) {
        //e.preventDefault();
        //e.redraw = false;

        //let base = 'https://rayv-webix4.jpl.nasa.gov/devel/ep/wp-json/dash/v1/ufs-weather-model/views/';

        //let url = `/${model.repo}/${model.metric}/`;

        // For testing only
        //let url = "https://jsonplaceholder.typicode.com/todos/1";
        model.owner = model.selectedOwner;
        model.repo = model.selectedRepo;
        model.metric = model.selectedMetric;
        model.loaded = false;
        model.error = "";

        if (model.startDate >= model.endDate) {
            model.startDate = getDefaultStartDate();
            model.endDate = getMaxDate();
        }
        
        let url = getUrl();
        updateData(url);
    }

	function getRepos() {
        let url = `${BASE_URL}${API_PATH}/repos/`;
		console.log("**** sending request ****" + url)
		return m.request(url);
	}

    function getData(repos) {
        console.log("**** RESPONSE ****", repos);
        model.repos = repos;
        model.minDate = getMinDate(model.owner, model.repo);
        let url = getUrl();
		console.log("**** sending request ****" + url)
        return m.request(url);
    }

	function setData(data) {
        console.log("**** RESPONSE ****", data);
        model.data = data;
        model.loaded = true;
	}

    function initData() {
        getRepos()
            .then(getData)
            .then(setData)
            .catch(function(e) {
                model.error = "Error loading data";
            });
    }

	function updateData(url) {
		headers = {};
		console.log("**** sending request ****" + url)
		return m.request({
			method: "GET",
			url: url,
			headers: headers,
		})
		.then(function(data){
            model.data = data
            if (! model.metric === "releases" && 
                ! model.metric === "contributors") {
                model.chart.data = model.data
                model.chart.update();
            }
            model.loaded = true;
            console.log("**** RESPONSE ****", data);
		})
        .catch(function(e) {
            model.error = "Error loading data";
        })
	}
    /***********************************************************/

    /************************** View Functions ***********************/
    function selectView(id, name,  options, callback) {

        let opts = options.map(function(option) {

            if (option.hasOwnProperty('owner')) {
                return m("option", {value: `${option.owner}/${option.name}`}, option.title);
            }
            else {
                return m("option", {value: option.name}, option.title);
            }

        });

        return m("select", {id: id, name: name, onchange: callback}, opts);
    }

    function formView(id, name, children) {

        return m("form", {id: id, name: name}, children);
    }

    function metricDataView(vnode) {
        let d = model.data;
        if (model.metric === "views" || model.metric === "clones") {
            let name = getName(METRICS, model.metric);
            let c = d['count'];
            let u = d['uniques'];

            return m("div.stats-container2", 
                [
                    m("div.stat-wrapper2", 
                    [
                        m("div.stat-label2", `Total ${name}`),
                        m("div.stat-value2", `${c}`)

                    ]),
                    m("div.stat-wrapper2", 
                    [
                        m("div.stat-label2", `Unique ${name}`),
                        m("div.stat-value2", `${u}`)

                    ]),
                
                ]

            );
        }
        return "";

    }

    function tableView(headers, data) {
        /*
         headers = ['header1', 'header2', 'header3'];
         data = [
             [1, 2, 3],
             [4, 5, 6],
             [7, 8, 9]
         ];
        */

        function get_row(lst, is_header=false) {
            let d = lst.map(function(item) {
                return is_header ? m("th", item) : m("td", item);
            });

            return m("tr", d);

        }

        function get_rows(lst) {
            return lst.map(function(inner_lst) {
                return get_row(inner_lst);
            });
        }

        let header_row = get_row(headers, true);
        let data_rows = get_rows(data);

        let children = [header_row].concat(data_rows);

        return m("table", {border: "1"}, children);
    }

    function metricDataViewTable(vnode) {
        let d = model.data;
        if (model.metric === "views" || model.metric === "clones") {
            let name = getName(METRICS, model.metric);
            let c = d['count'];
            let u = d['uniques'];

            let header_row = [`Total ${name}`, `Unique ${name}`];
            let data_rows = [
                [`${c}`, `${u}`]
            ];

            return tableView(header_row, data_rows);

        }
        return "";
    }

    function createChart(vnode) {
        const ctx = vnode.dom.getContext('2d');

        model.chart = new Chart(ctx, {
            type: "line",
            data: model.data,
            options: CHART_OPTS
            });
    }

    function chartView(vnode) {
        return [m("canvas#chart", {oncreate: createChart}), metricDataView()];
        //return [m("canvas#chart", {oncreate: createChart}), metricDataViewTable()];
    }

    function buttonView(label, callback){
        return m("button", {type: "button", onclick: callback}, label);
    }

    function startDateCallback(e) {

        if (e.target.value < model.minDate) {
            model.startDate = model.minDate;
        }
        else if (e.target.value >= getMaxDate()) {
            model.startDate = addDays(getMaxDate(), -1);
        }
        else {
            model.startDate = e.target.value;
        }
    }

    function endDateCallback(e) {
    
        if (e.target.value > getMaxDate()) {
            model.endDate = getMaxDate();
        }
        else if (e.target.value <= model.minDate) {
            model.endDate = addDays(model.minDate, 1);
        }
        else {
            model.endDate = e.target.value;
        }
    }

    function datePickerView(name, value, start, end, cb) {
        let st = {visibility: model.showDatePicker ? "visible" : "hidden"};
        //let st = {display: model.showDatePicker ? "inline" : "none"};
        let attrs = {type: "date",
            id: name, 
            name: name, 
            value: value, 
            min: start, 
            max: end, 
            onchange: cb,
            style: st,
        }
        return m("input", attrs);
    }

    function contributorViewList(vnode) {
        let data = model.data;
        let count = data['count'];

        let h3 = m("h3", {}, `Total Number of Contributors: ${count}`);

        let children = data['top'].map(function(item) {
            let login = item['login'];
            let contrib = item['contributions'];
            let txt = `${login} (${contrib})`;
            return m("li", {}, txt);

        });

        return m("div", {style: {display: "block"}}, [h3, m("h4", {}, "Top Contributors"), m("ol", {}, children)])

        //return [h3, m("ol", {style: {display: "none"}}, children)];

    }

    function contributorViewTable(vnode) {
        let data = model.data;
        let count = data['count'];

        let h3 = m("h3", {}, `Total Number of Contributors: ${count}`);

        let header_row = ['User Name', 'Number of Contributions'];
        
        let data_rows = data['top'].map(function(item) {
            return [item["login"], item["contributions"]];
            });



        let table = tableView(header_row, data_rows);
         
        return [h3, table];
    }

    function releaseViewList(vnode) {
        let data = model.data.releases;

        let children = data.map(function(item) {
            let name = item['name'];
            let d = item['date'];
            let txt = `${name} - ${d}`;
            return m("li", {}, txt);
        });

        return m("div", {}, [m("ol", {}, children)]);
    }

    function releaseViewTable(vnode) {
        let data = model.data.releases;

        let header_row = ['Name', 'Release Date']

        let data_rows = data.map(function(item) {
            return [item["name"], item["date"]];
            });

        let table = tableView(header_row, data_rows);
         
        return table;
    }

    function dataView(vnode) {
        if (model.metric === "releases") 
            //return releaseViewList(vnode);
            return releaseViewTable(vnode);
        
        if (model.metric === "contributors")
            //return contributorViewList(vnode);
            return contributorViewTable(vnode);

        return chartView(vnode);
    }

    function view(vnode) {

        if (! model.repos) {
            return m('div.loader');
        }
        
        let repoLabel = m("label", {for: 'repo-select'}, "Repository");
        let repoSelect = selectView('repo-select', 'repo-select', model.repos, repoCallback);

        let metricLabel = m("label", {for: 'metric-select'}, "Metric");
        let metricSelect = selectView('metric-select', 'metric-select', METRICS, metricCallback);

        let btn = buttonView('Submit', submitCallback);


        if (model.selectedMetric === 'views' || model.selectedMetric === 'clones')
            model.showDatePicker = true;
        else 
            model.showDatePicker = false;
        

        let min = getMinDate(model.selectedOwner, model.selectedRepo);
        let max = getMaxDate();
        let startDp = datePickerView('start', model.startDate, model.minDate, max, startDateCallback);
        let endDp = datePickerView('end', model.endDate, model.minDate, max, endDateCallback);

        let frm = formView('dash-form', 'dash-form', [repoLabel, repoSelect, metricLabel, metricSelect, startDp, endDp, btn]);


        let dv = null;

        if (model.error)
            dv = m("div", model.error);

        else if (!model.loaded || !model.data)
            //dv = m("div", "Loading...");
            dv = m("div.loader");

        else if (model.data.hasOwnProperty('message'))
            dv = m("div", model.data.message);

        else
            dv = dataView(vnode);


        return [
            frm, 
            dv
        ];


    }
    /*****************************************************************/

	function init(vnode){
        // let url = "https://jsonplaceholder.typicode.com/todos/1";
        //let url = "https://rayv-webix4.jpl.nasa.gov/devel/ep/wp-json/dash/v1/ufs-weather-model/views/";

        return initData();
	}

    return {
        oninit: init,
        view: view,
        }
}

let root = document.getElementById('dashboard-app');


m.mount(root, Dash);






