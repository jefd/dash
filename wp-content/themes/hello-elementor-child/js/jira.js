//const BASE_URL =  "https://epic.noaa.gov";
const BASE_URL =  "https://rayv-webix4.jpl.nasa.gov/devel/ep";
//const BASE_URL =  "";

const API_PATH = "/wp-json/jira/v1/1/14";

const URL = `${BASE_URL}${API_PATH}`

const SYSTEM = [
    {id: '10300', display: 'Active Directory'},
    {id: '10301', display: 'Analytics and Reporting Service'},
    {id: '10302', display: 'Billing Services'},
    {id: '10303', display: 'Cloud Storage Services'},
    {id: '10304', display: 'Data Center Services'},
    {id: '10305', display: 'Email and Collaboration Services'},
    {id: '10306', display: 'Financial Services'},
    {id: '10307', display: 'HR Services'},
    {id: '10308', display: 'Intranet'},
    {id: '10309', display: 'Jira'},
    {id: '10310', display: 'Office Network'},
    {id: '10311', display: 'Payroll Services'},
    {id: '10312', display: 'Printers'},
    {id: '10313', display: 'Public Website'},
    {id: '10314', display: 'VPN Server'},
    {id: '10315', display: 'Webstore Purchasing Services'},
]

const PRIORITY = [
    {id: '10200', display: 'Blocker'},
    {id: '2', display: 'High'},
    {id: '3', display: 'Medium', 'selected': true},
    {id: '4', display: 'Low'},
    {id: '10201', display: 'Minor'},
]

const IMPACT = [
    {id: '0', display: 'None'},
    {id: '10512', display: 'Extensive / Widespread'},
    {id: '10513', display: 'Significant / Large'},
    {id: '10514', display: 'Moderate / Limited'},
    {id: '10515', display: 'Minor / Localized'},
]


function Jira(initialVnode) {

    let model = {
        summary: '',
        description: '',
        fileElt: null,
        formData: null,
        fileMap: {},
        system: null,
        priority: '3',
        impact: '0',
        data: null,
	    loaded: false,	
        error: "",
        showForm: true,
    };

    function getUrl() {
        return `${BASE_URL}${API_PATH}/${model.owner}/${model.repo}/${model.metric}/?start=${model.startDate}&end=${model.endDate}`;
    }


    /******************** Update Functions *********************/
    function summaryCallback(e) {
        model.summary = e.target.value;
    }

    function descriptionCallback(e) {
        model.description = e.target.value;
    }

    function systemCallback(e) {
        model.system = [];

        for (let i = 0; i < e.target.selectedOptions.length; i++) {
            model.system.push(e.target.selectedOptions[i].value);
        }
    }

    function fileCallback(e) {
        for (const file of model.fileElt.files) {
            let key = `${file.size}-${file.lastModified}`;
            model.fileMap[key] = file;
        }
    }

    function priorityCallback(e) {
        model.priority = e.target.value;
    }

    function impactCallback(e) {
        model.impact = e.target.value;
    }

    function linkCallback(e) {
        e.preventDefault();
        if (model.showForm)
            model.showForm = false;
        else
            model.showForm = true;

    }
    
    function submitCallback(e) {
        e.preventDefault();
        model.loaded = false;
        model.error = "";

        //console.log(model);

        if (!model.summary || !model.description) {
            model.error = 'Summary and description are both required fields';
            return
        }

        model.formData = new FormData();
        model.formData.set('summary', model.summary);
        model.formData.set('description', model.description);

        if (model.system) {
            for (item of model.system) {
                model.formData.append('system', item);
            }
        }

        model.formData.set('priority', model.priority);

        if (model.impact !== '0')
            model.formData.set('impact', model.impact);

        let files = Object.values(model.fileMap);
        for (file of files) {
            model.formData.append('files', file);
        }

        console.log('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        //console.log(model.formData.getAll('files'));
        //console.log(model.formData);
        for (const value of model.formData.values()) {
            console.log(value);
        }
        console.log('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');



        //let url = getUrl();
        //updateData(url);
    }

    function getData(repos) {
        console.log("**** RESPONSE **** ", repos);
        model.repos = repos;
        model.minDate = getMinDate(model.owner, model.repo, model.metric);
        let url = getUrl();
		console.log("**** sending request **** " + url)
        return m.request(url);
    }

	function setData(data) {
        console.log("**** RESPONSE **** ", data);
        model.data = data;
        model.loaded = true;
	}

    function initData() {
    }

	function updateData(url) {
	}
    /***********************************************************/

    /************************** View Functions ***********************/
    function selectView(name, data, multi, callback) {
        let opts = data.map(function(option) {
            if ('selected' in option)
                return m("option", {value: option.id, selected: 'true'}, option.display);
            else
                return m("option", {value: option.id}, option.display);
        }
        );

        if (multi)
            return m("select", {id: name, name: name, multiple: true, size: SYSTEM.length, onchange: callback}, opts);
        return m("select", {id: name, name: name, onchange: callback}, opts);
    }

    function formView(name, children) {

        return m("form", {id: name, name: name}, children);
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


    function mkFileElt(vnode) {
        model.fileElt = vnode.dom;
    }

    function fileListView(vnode) {
        function clearCallback(e) {
            e.preventDefault();
            delete model.fileMap[e.target.id];
        }

        function clearAllCallback(e) {
            e.preventDefault();
            model.fileMap = {};
        }

        let lst = [];
        let files = Object.values(model.fileMap);

        
        for(file of files)
        {
            let uid = `${file.size}-${file.lastModified}`;
            lst.push(m('li', {}, m('a', {style: {'margin-right': '10px'}, id: uid, href: '', onclick: clearCallback}, '[X]'), m('a', {}, file.name)));
        }


        let filesView = m('ul', {}, [lst, lst.length != 0 ? 
            m('a', {href: '', onclick: clearAllCallback}, 'Clear file list') : 
            null]);
        return filesView
    }

    function view(vnode) {
        let summaryLabel = m("label", {for: 'summary'}, "Summary:");
        let summaryField = m('input', {onchange: summaryCallback, 
                                       id:'summary', 
                                       name: 'summary', 
                                       type: 'text', 
                                       placeholder: 'Enter a summary'})

        let descriptionLabel = m("label", {for: 'description'}, "Description:");
        let descriptionField = m('textarea', {onchange: descriptionCallback, 
                                              id: 'description', 
                                              name: 'description', 
                                              rows: '5', 
                                              cols: '60', 
                                              placeholder: 'Enter description'})

        let fileLabel = m("label", {class: 'button' , for: 'my-files'}, "Upload files");
        let fileField = m('input', {style: {opacity: '0'},
                                    onchange: fileCallback, 
                                    oncreate: mkFileElt, 
                                    id:'my-files', 
                                    name: 'my-files', 
                                    type: 'file', 
                                    multiple: true})

        let systemLabel = m("label", {for: 'system-select'}, "Select a system:");
        let systemSelect = selectView('system-select', SYSTEM, multi=true, systemCallback);

        let priorityLabel = m("label", {for: 'priority-select'}, "How urgent is this?");
        let prioritySelect = selectView('priority-select', PRIORITY, multi=false, priorityCallback);

        let impactLabel = m("label", {for: 'impact-select'}, "What is the impact?");
        let impactSelect = selectView('impact-select', IMPACT, multi=false, impactCallback);

        let btn = m("button", {class: 'button', type: "button", onclick: submitCallback}, 'Submit');

        let frm = formView('jira-form', [
                                         m('p', {}, [summaryLabel, m('br'), summaryField]), 
                                         m('p', {}, [descriptionLabel, m('br'), descriptionField]), 
                                         m('p', {}, [fileLabel, m('br'), fileField]), 
                                         fileListView(vnode),   
                                         m('p', {}, [systemLabel, m('br'), systemSelect]), 
                                         m('p', {}, [priorityLabel, m('br'), prioritySelect]),
                                         m('p', {}, [impactLabel, m('br'), impactSelect]),
                                         btn,


        ]);

        let link = m('a', {href: '', onclick: linkCallback}, model.showForm ? 'Hide Form': 'Create Service Desk Request');

        return [
            link,
            model.showForm ? frm : null, 
            model.error ? m('div', {style: {color: 'red'}}, model.error) : null,
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

let root = document.getElementById('jira-app');


m.mount(root, Jira);






