Mautic.getOptionsFromField = function(field) {
    var fieldId = mQuery(field).val();
    const blocks = mQuery(field).parent()
        .parent()
        .parent()
        .parent()
        .find('.row');

    Mautic.ajaxActionRequest('plugin:LeuchtfeuerMultiselectHandling:getMultiselectOptions', {
        id: fieldId
    }, function (response) {
        var selectAdd = mQuery(blocks[1]).find('select');
        var selectRemove = mQuery(blocks[2]).find('select');
        selectAdd.empty();
        selectRemove.empty();
        console.log('Cleared selectAdd and selectRemove',fieldId);
        // Add new options from response.data
        if (response.data && Array.isArray(response.data)) {
            console.log('Adding options to selectAdd and selectRemove', response.data);
            response.data.forEach(function(item) {
                var optionAdd = mQuery('<option></option>')
                    .val(item.value)
                    .text(item.label);
                var optionRemove = mQuery('<option></option>')
                    .val(item.value)
                    .text(item.label);
                selectRemove.append(optionRemove);
                selectAdd.append(optionAdd);
            });
        }
        selectAdd.trigger('chosen:updated');
        selectRemove.trigger('chosen:updated');
    }, false, false, "GET");
}
