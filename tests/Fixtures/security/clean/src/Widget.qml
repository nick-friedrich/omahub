import QtQuick 2.15

Widget {
    property string label: "Example"
    Rectangle {
        width: 120
        height: 40
        Text { text: label }
    }
}
