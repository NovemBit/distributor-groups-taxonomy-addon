document.addEventListener('DOMContentLoaded', function() {
    const dataNode = document.getElementById('active-groups-ids');
    if (
        typeof dataNode !== undefined &&
        dataNode !== null &&
        typeof dataNode.dataset !== undefined &&
        dataNode.dataset !== null
    ) {
        const groupsData = dataNode.dataset.groups;
        if (groupsData && typeof groupsData !== undefined && groupsData !== null) {
            let ids = JSON.parse(groupsData);
            ids.map(id => {
                let checkBox = document.getElementById(
                    `in-dt_ext_connection_group-${id}`
                );
                checkBox.checked = true;
                checkBox.onclick = () => false;
            });
        }
    }
    let checklist = document.getElementById('dist-group-checklist');
    if(checklist) {
		let expand = checklist.querySelectorAll('.is-parent');
		if(expand){
			for( item of expand ){
				item.classList.add("open");
			}
		}
		const chbs = checklist.querySelectorAll('input[type=checkbox]');
		for (let i = 0; i < chbs.length; i++) {
			chbs[i].onclick = function () {
				let childes = this.parentElement.parentElement.querySelectorAll('input[type=checkbox]');
				for (let j = 0; j < childes.length; j++) {
					if (this.checked) {
						childes[j].checked = true;
					} else {
						childes[j].checked = false;
					}
				}
			}
		}
	}
});
